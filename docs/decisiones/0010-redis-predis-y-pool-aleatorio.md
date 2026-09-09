# ADR-0010 — Cliente Redis `predis` y diseño del pool aleatorio

**Estado:** aceptado

## Contexto

La "botella al mar" (ADR-0004) necesita elegir un destinatario al azar entre miles de usuarios.
`inRandomOrder()` hace un *full scan* y no escala (`backend-garden/CLAUDE.md`, `docs/api/botella-al-mar.md`).
La solución del contrato es un `SET` de Redis con los IDs elegibles, refrescado cada 15 min, del que se
saca un miembro con `SRANDMEMBER` y se verifica puntualmente contra la base de datos.

Dos problemas de entorno:

1. El `.env` traía `REDIS_CLIENT=phpredis`, y la extensión `phpredis` **no está cargada** en el PHP 8.4
   de Laragon ni garantizada en CI.
2. Meter Redis en la ruta de los tests los vuelve frágiles (hace falta un servicio Redis en CI y
   `flushdb` entre casos).

## Decisión

- **`REDIS_CLIENT=predis`** (`.env` y `.env.example`), con `predis/predis` como dependencia directa.
  Es PHP puro, sin extensión. `QUEUE_CONNECTION` y `CACHE_STORE` siguen en `database` (Horizon sigue
  diferido); Redis se usa **solo** para el pool aleatorio de momento.

- **El pool es una interfaz**: `App\Services\Postal\RandomRecipientPool`
  (`replace/random/forget/count/all`). Implementaciones:
  - `RedisRandomRecipientPool` — producción. `replace()` construye un `SET` temporal y hace `RENAME`
    atómico.
  - `ArrayRandomRecipientPool` — dev sin Redis y **doble de test**. Se enlaza con
    `app()->instance(RandomRecipientPool::class, …)` en los tests.

- **La corrección no depende de la exactitud del pool.** El pool es una pista con hasta 15 min de
  desfase; `RandomRecipientPicker` siempre revierte cada candidato contra la BD
  (`RandomRecipientQuery::isEligible`): opt-in, activo ≤30 d, sin bloqueo mutuo, bajo su cap diario, y
  sin carta de ese mismo remitente en 90 d. Tras N intentos fallidos → `failed` con `no_recipient`, la
  carta vuelve a borradores y se avisa al remitente.

- `RefreshRandomRecipientPoolJob` (cada 15 min, cola `maintenance`) solo aplica las condiciones
  **independientes del remitente**; las dependientes (bloqueo, cap, cooldown) las pone el picker.

## Consecuencias

- CI y la suite local corren sin Redis. Un despliegue real necesita el servidor Redis que Laragon ya
  provee; contenerizarlo va con la tarea de Horizon.
- Si más adelante se mueve `queue`/`cache` a Redis, este ADR no cambia: el pool ya está aislado tras su
  interfaz.
- El `held` (carta aleatoria retenida por el filtro para revisión humana) es un estado nuevo de
  `DeliveryStatus` que **nunca** entra en el set de despacho.
