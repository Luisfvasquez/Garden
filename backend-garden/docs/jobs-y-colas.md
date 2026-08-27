# Jobs, colas y el reloj postal

Documentación **interna** del backend. El contrato de la API está en `../../docs/api/`.

## Colas

| Cola | Prioridad | Contenido |
| --- | --- | --- |
| `critical` | máxima | Moderación crítica, alertas de seguridad |
| `postal` | alta | Despacho y entrega de cartas |
| `notifications` | media | Push, email, database |
| `moderation` | media | Filtros automáticos |
| `default` | baja | Miscelánea |
| `maintenance` | mínima | Purgas, recálculos, métricas |

Horizon con workers separados por cola, `balance: auto`, `maxProcesses` diferenciados.

## Scheduler

```php
// routes/console.php
Schedule::job(new DispatchDueLettersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new DeliverArrivedLettersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new RefreshRandomRecipientPoolJob)->everyFifteenMinutes();
Schedule::job(new GenerateUpcomingDeliveriesJob)->dailyAt('03:00');
Schedule::job(new ExpireStaleDollRequestsJob)->hourly();
Schedule::job(new SendQueuedQuietHourPushJob)->everyTenMinutes();
Schedule::job(new PurgeOldDollChatsJob)->dailyAt('04:00');
Schedule::job(new ProcessAccountDeletionsJob)->dailyAt('04:30');
Schedule::job(new RecalculateDollRatingsJob)->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
```

## El patrón de reserva por lote

**Trampa:** `chunkById` filtrando por `status` y modificando `status` dentro del bucle **salta
registros**, porque el conjunto cambia entre páginas.

Solución: reservar primero de forma atómica, procesar después.

```php
class DispatchDueLettersJob implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 300;

    public function handle(): void
    {
        $batchId = (string) Str::uuid();

        $claimed = LetterDelivery::query()
            ->where('status', DeliveryStatus::Queued)
            ->where('scheduled_for', '<=', now())
            ->whereNull('dispatch_batch_id')
            ->limit(500)
            ->update(['dispatch_batch_id' => $batchId]);

        if ($claimed === 0) {
            return;
        }

        LetterDelivery::where('dispatch_batch_id', $batchId)
            ->cursor()
            ->each(fn ($d) => DispatchSingleLetterJob::dispatch($d->id));
    }
}
```

`DispatchSingleLetterJob` es idempotente: si el estado ya no es `queued`, sale en silencio.
`DeliverArrivedLettersJob` usa el mismo patrón filtrando `in_transit` + `delivered_at <= now()`.

## Orden de comprobaciones al despachar

1. ¿Sigue en `queued`? Si no → salir.
2. Si es aleatoria y no tiene destinatario → `RandomRecipientPicker::pick()`. Si no hay → `failed`.
3. ¿El destinatario ha bloqueado al remitente? → `blocked` (el remitente verá `delivered`).
4. ¿El destinatario está `active`? Si no → `failed` + notificar al remitente + devolver a borradores.
5. Calcular tránsito, pasar a `in_transit`, registrar `delivery_events`, emitir `LetterDispatched`.

## Reintentos

`tries = 3`, `backoff = [60, 300, 900]`. Un job fallido **nunca** deja una entrega en estado
intermedio: transacción + estado explícito `failed` con `failure_reason`.

## Alerta crítica: el reloj postal

Si `DispatchDueLettersJob` no procesa nada durante 15 minutos **habiendo entregas vencidas**, el reloj
está roto y las cartas no llegan. Es la métrica más importante del sistema. Alerta inmediata.

## Nota sobre `delivered_at`

Mientras el estado es `in_transit`, `delivered_at` funciona como **fecha objetivo**; se confirma al
pasar a `delivered`. Si el proyecto crece, considera separar en `arrives_at` (objetivo) y `delivered_at`
(confirmación real).
