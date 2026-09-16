# API — Bloqueos, reportes, notificaciones y utilidades

Estado: `[x] contrato definido` · `[~] backend` · `[ ] front`

> Backend hecho: **bloqueos** (`GET/POST/DELETE /blocks`, silencioso, ya consumido por el despacho),
> **reportes** (`POST /reports`, severidad automática, `throttle:report` 10/h, críticos → `Log::critical`
> + correo real vía `CriticalAlertDispatcher`, ver más abajo) y
> **notificaciones** in-app + email: eventos de dominio (`LetterDispatched`/`LetterDelivered`/
> `LetterFailed`/`LetterRead`) → listeners → `Notification` (`database` + `mail` según
> `user_settings`), y `GET /notifications` · `/unread-count` · `/{id}/read` · `/read-all`.
> **Fase 2A:** `support_resources` + `GET /support-resources?country_code=&topic=` (público, devuelve
> las líneas del país + el fallback internacional, orden por `priority`).
> **Fase 2E — Web Push:** `push_subscriptions` (`POST` / `DELETE /push-subscriptions/{id}`,
> `updateOrCreate` por endpoint/device_token), `GET /push/vapid-public-key` (público, `enabled:false`
> si no hay claves). Canal `WebPushChannel` (clase, no driver con nombre): en vez de enviar, inserta en
> `scheduled_pushes` con `deliver_after` = ahora, o el fin de la ventana `quiet_hours` en hora local
> del usuario (puede cruzar medianoche). `DispatchDuePushesJob` (cada minuto, cola `notifications`)
> agrupa por `(user, type)` → una sola notificación ("N novedades nuevas") y poda suscripciones caídas.
> `WebPushClient` es interfaz: `MinishlinkWebPushClient` (VAPID) / `NullWebPushClient` (sin claves) /
> doble de test. La llegada anónima nunca nombra al remitente en el payload.
> **Pendiente:** `GET /features` per-usuario, push nativo iOS/Android (Fase 4).
> **Desviación:** `notify_on_dispatch_confirm` cubre a la vez el aviso de *despacho* y el de *entrega*
> al remitente (el spec tiene un solo flag).

## Bloqueos

```
GET    /api/v1/blocks
POST   /api/v1/blocks          # { user_id | postal_handle, reason? }
DELETE /api/v1/blocks/{userId}
```

`GET /blocks` devuelve `{ data: [{ id, reason, created_at, user: { id, postal_handle, display_name, avatar_url } }] }`.
`user.id` es el `blocked_id`, que necesitas para `DELETE /blocks/{userId}`.

El bloqueo afecta a: cartas dirigidas, asignación aleatoria, comentarios y solicitudes a Dolls.

**Es silencioso.** El bloqueado nunca lo sabe: sus entregas pasan a `blocked` pero él ve `delivered`.

## Reportes

```
POST /api/v1/reports
```

```json
{
  "reportable_type": "letter_delivery",
  "reportable_id": "01J8...",
  "category": "harassment",
  "details": "..."
}
```

`reportable_type`: `letter_delivery` | `public_post` | `comment` | `user` | `doll_chat_message`.
`category`: `harassment` | `sexual` | `hate` | `violence` | `self_harm` | `spam` | `minor_safety` | `other`.

**Escalado crítico:** `minor_safety` y `self_harm` con severidad `critical` generan alerta inmediata al
equipo — `Log::critical` siempre, y correo real (`CriticalAlertDispatcher` → `MODERATION_ALERT_EMAIL`)
cuando está configurado —, no esperan en la cola de moderación
(`backend-garden/docs/moderacion.md`).

## Notificaciones

```
GET  /api/v1/notifications              # cursor
POST /api/v1/notifications/{id}/read
POST /api/v1/notifications/read-all
GET  /api/v1/notifications/unread-count
POST /api/v1/push-subscriptions
DELETE /api/v1/push-subscriptions/{id}
GET  /api/v1/push/vapid-public-key      # público
```

### POST /push-subscriptions

```json
{
  "platform": "web",
  "endpoint": "https://fcm.googleapis.com/...",
  "public_key": "...",
  "auth_token": "...",
  "device_name": "Chrome en Mac"
}
```

Para móvil: `platform: "ios"|"android"` y `device_token` en lugar de `endpoint`.

### Reglas

- Respeta `quiet_hours` en hora local: si cae dentro, encola el push hasta la hora de fin.
- Agrupa: 3 cartas en 10 minutos → una sola notificación.
- **La notificación de llegada nunca revela contenido ni remitente** si la carta es anónima.
- Todo email lleva enlace de preferencias y header `List-Unsubscribe`.

## Utilidades

```
GET /api/v1/health                       # público. db, redis, queue, reverb
GET /api/v1/features                     # flags activos para este usuario
GET /api/v1/support-resources?country_code=ES&topic=self_harm
```

`GET /features` lo consultan PWA y app móvil al arrancar. Permite apagar un módulo en producción sin
desplegar clientes.

`GET /support-resources` devuelve líneas de ayuda del país del usuario **más** el fallback
internacional (`country_code: null`), ordenadas por `priority` descendente. Filtro opcional `?topic=`
(`self_harm` | `grief` | `general`). Se usa cuando el filtro detecta señales de riesgo, y debe estar
accesible siempre desde los ajustes.
