# API — Bloqueos, reportes, notificaciones y utilidades

Estado: `[ ] contrato definido` · `[ ] backend` · `[ ] front`

## Bloqueos

```
GET    /api/v1/blocks
POST   /api/v1/blocks          # { user_id | postal_handle }
DELETE /api/v1/blocks/{userId}
```

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
equipo (email/Slack), no esperan en la cola de moderación.

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
GET /api/v1/support-resources?country_code=ES
```

`GET /features` lo consultan PWA y app móvil al arrancar. Permite apagar un módulo en producción sin
desplegar clientes.

`GET /support-resources` devuelve líneas de ayuda del país del usuario. Se usa cuando el filtro detecta
señales de riesgo, y debe estar accesible siempre desde los ajustes.
