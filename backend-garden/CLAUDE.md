# backend-garden — Laravel API

API REST desacoplada. **No renderiza vistas.** Todo cliente (PWA, iOS, Android) consume el mismo
contrato definido en `../docs/api/`.

> Lee también `../CLAUDE.md` (reglas transversales del monorepo).

## Stack

Laravel 11 · PHP 8.3 · PostgreSQL 16 · Redis 7 · Horizon · Reverb · Sanctum · Pest · Pint · Larastan 6

## Estructura esperada

```
app/
├── Console/Commands/
├── Enums/                    # UserRole, DeliveryStatus, LetterKind...
├── Events/                   # LetterDispatched, LetterArrived, DollRequestUpdated...
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/             # un FormRequest por acción, siempre
│   ├── Resources/            # un API Resource por modelo, siempre
│   └── Middleware/
├── Jobs/
│   ├── Postal/
│   ├── Moderation/
│   └── Maintenance/
├── Listeners/
├── Models/
├── Notifications/
├── Policies/
└── Services/
    ├── Postal/               # TransitCalculator, RandomRecipientPicker, DeliveryStateMachine
    ├── Moderation/           # ContentModerator (interfaz + drivers)
    └── Scheduling/           # OccurrenceGenerator
```

## Convenciones no negociables

- **Nunca `return $model`.** Toda respuesta pasa por un API Resource.
- **Nunca `$request->all()`.** Siempre un FormRequest con reglas explícitas.
- **Nunca `if ($user->role === 'admin')` en un controlador.** La autorización vive en Policies.
- **Transiciones de estado por métodos del modelo** (`markDispatched()`, `markDelivered()`), que validan
  el estado origen y registran en `delivery_events`. Nada de `->update(['status' => ...])` suelto.
- **Todo job es idempotente.** Comprueba el estado antes de actuar y sale en silencio si ya se procesó.
- **Todo endpoint de escritura tiene rate limit.** Ver sección 11.4 del spec.
- IDs UUID en todas las tablas de dominio.
- Enums nativos de PHP para todo campo con valores cerrados. Nunca strings sueltos.
- `declare(strict_types=1)` en todos los archivos nuevos.

## Documentación interna de este repo

| Archivo | Contenido |
| --- | --- |
| `docs/jobs-y-colas.md` | Despacho, entrega, scheduler, idempotencia |
| `docs/moderacion.md` | Capas del filtro, drivers, escalado crítico |
| `docs/migraciones.md` | Orden, compatibilidad hacia atrás, índices |

El **contrato** de la API no vive aquí, vive en `../docs/api/`.

## Definición de "terminado" para un módulo

- [ ] Endpoints implementados y coincidentes con `../docs/api/{modulo}.md`
- [ ] FormRequests con validación completa
- [ ] API Resources sin filtrar campos sensibles
- [ ] Policy con tests: un usuario no puede acceder a datos de otro
- [ ] Feature tests de cada endpoint (200, 401, 403, 422)
- [ ] Rate limit aplicado
- [ ] Pint + PHPStan en verde
- [ ] `../docs/api/{modulo}.md` actualizado si hubo desviaciones
- [ ] Casilla marcada en `../docs/progreso.md`

## Trampas conocidas

- **`chunkById` filtrando por un campo que modificas dentro del bucle salta registros.** Usa el patrón
  de reserva por lote con `dispatch_batch_id` (ver `docs/jobs-y-colas.md`).
- **`letters.body` está cifrado**, así que `WHERE body LIKE` no funciona. Usa `body_plain`.
- **`inRandomOrder()` no escala.** El selector aleatorio usa un pool en Redis.
- **No precalcules fechas UTC a 10 años vista.** Guarda la zona local y convierte al generar.
