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
// routes/console.php — estado real tras la Fase 3 (+ la alarma de 5A)
Schedule::job(new DispatchDueLettersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new DeliverArrivedLettersJob)->everyMinute()->withoutOverlapping();
Schedule::command('postal:health')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
Schedule::job(new GenerateUpcomingDeliveriesJob)->dailyAt('03:00')->withoutOverlapping();
Schedule::job(new RefreshRandomRecipientPoolJob)->everyFifteenMinutes();
Schedule::job(new DispatchDuePushesJob)->everyMinute()->withoutOverlapping();
Schedule::job(new ExpireStaleDollRequestsJob)->hourly()->withoutOverlapping();
Schedule::job(new RecalculateDollRatingsJob)->hourly()->withoutOverlapping();
Schedule::job(new PurgeOldDollChatsJob)->dailyAt('04:00')->withoutOverlapping();
```

**Todavía no existen** (van con su módulo): `ProcessAccountDeletionsJob` (borrado diferido de cuentas,
la gracia de 30 días la aplica hoy la propia consulta) y `horizon:snapshot` (Horizon sigue diferido con
la contenerización). `SendQueuedQuietHourPushJob` del plan original se implementó como
`DispatchDuePushesJob`, cada minuto: la cola de `quiet_hours` se vacía sola al vencer `deliver_after`.

### Los dos jobs de Dolls que no son "otro tick más"

`RecalculateDollRatingsJob` recalcula el agregado de `doll_profiles` **entero, desde cero**, en un solo
UPDATE correlacionado. No incrementa: una media mantenida a incrementos se desvía en cuanto una fila se
borra o se corrige, y el agregado es un valor derivado — `doll_requests` es la verdad.

`PurgeOldDollChatsJob` borra **sólo `doll_chat_messages`** de solicitudes en estado terminal cerradas
hace más de `dolls.chat_retention_days` (90). La solicitud sobrevive (auditoría y valoración) y la carta
también — es del cliente. Una conversación abierta no se toca nunca, por vieja que sea.

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

Lo vigila **`postal:health`**, cada 5 minutos. Falla (exit 1) ante cualquiera de estas tres cosas:

| Condición | Umbral | Qué significa |
| --- | --- | --- |
| `queued` vencidas y ningún despacho reciente | 15 min de gracia + 15 min de silencio | El scheduler o el worker están muertos |
| `in_transit` con `delivered_at` pasado | 15 min de gracia | `DeliverArrivedLettersJob` no las mueve |
| `queued` con `dispatch_batch_id` asignado | 30 min | Reserva huérfana: el despachador las ignora para siempre (runbook §1) |

La gracia existe porque los dos ticks corren cada minuto: sin ella, una entrega vencida hace 30
segundos dispararía la alarma y el aviso dejaría de significar nada. Por el mismo motivo sólo sale
**un correo por incidente** (`postal.health.alert_cooldown_minutes`, 60 por defecto) en lugar de uno
cada 5 minutos mientras dure.

El correo va a `OPS_ALERT_EMAIL` y viaja por la cola `critical` — con la trampa evidente de que si lo
que ha muerto es el worker, el correo espera con él. **El exit code es la señal que no depende de
ninguna cola**, y es la que debe enganchar el monitor externo (5A). Vacío el destino, la alerta se
queda sólo en el log.

`postal:stats` enseña los mismos números sin juzgarlos, y siempre sale 0. Ninguno de los dos escribe:
liberar un lote huérfano sigue siendo la decisión humana que describe el runbook.

## Nota sobre `delivered_at`

Mientras el estado es `in_transit`, `delivered_at` funciona como **fecha objetivo**; se confirma al
pasar a `delivered`. Si el proyecto crece, considera separar en `arrives_at` (objetivo) y `delivered_at`
(confirmación real).
