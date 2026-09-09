# Convenciones de la API

**Leer antes de crear o consumir cualquier endpoint.**

## Base

- Prefijo: `/api/v1`. Toda ruptura de contrato genera `/api/v2`; ambas conviven durante la transición.
- `Accept: application/json` obligatorio. Respuestas siempre JSON.
- URLs en plural y kebab-case. Cuerpos JSON en `snake_case`.
- Fechas ISO-8601 UTC con sufijo `Z`: `2027-04-12T09:00:00Z`. El cliente convierte a local.
- IDs: UUID (string). Nunca asumir orden ni secuencialidad.

## Autenticación

| Cliente | Modo |
| --- | --- |
| PWA (mismo dominio raíz) | Cookie de sesión Sanctum. Antes del primer POST: `GET /sanctum/csrf-cookie` |
| App móvil | `Authorization: Bearer {token}` obtenido en `POST /api/v1/auth/token` |

Endpoints públicos: `GET /posts`, `GET /posts/{slug}`, `GET /tags`, `GET /health`,
`GET /support-resources`, `GET /features`. Todo lo demás requiere autenticación.

Endpoints que además requieren email verificado: enviar cartas, botella al mar, publicar en el blog,
crear solicitudes a Dolls.

## Formato de respuesta

Recurso único:

```json
{ "data": { "id": "01J8...", "...": "..." } }
```

Colección paginada (cursor):

```json
{
  "data": [ ... ],
  "meta": { "per_page": 20, "next_cursor": "eyJpZCI6...", "has_more": true }
}
```

**Siempre cursor pagination en feeds y listados que cambian** (buzón, envíos, blog, mensajes). Offset
solo en catálogos estáticos.

## Formato de error

```json
{
  "message": "El destinatario no acepta cartas de este tipo.",
  "error_code": "RECIPIENT_NOT_ACCEPTING",
  "errors": { "recipient_id": ["No acepta cartas anónimas."] },
  "meta": { "request_id": "01J8..." }
}
```

**El cliente reacciona a `error_code`, nunca al texto de `message`** (que es traducible y puede cambiar).

### Códigos HTTP

| Código | Uso |
| --- | --- |
| 200 | OK |
| 201 | Recurso creado |
| 204 | Sin contenido (borrados) |
| 401 | No autenticado |
| 403 | Autenticado pero sin permiso (policy) |
| 404 | No existe **o no es visible para este usuario** (no reveles existencia) |
| 409 | Conflicto de estado (ej. cancelar una carta ya entregada) |
| 422 | Validación |
| 429 | Rate limit. Incluye header `Retry-After` |
| 426 | Versión de app obsoleta (header `X-App-Version`) |

### Catálogo de `error_code`

| Código | Significado |
| --- | --- |
| `INVALID_CREDENTIALS` | Email o contraseña incorrectos |
| `UNDER_MINIMUM_AGE` | No cumple la edad mínima (16) en el registro |
| `EMAIL_NOT_VERIFIED` | Requiere verificar el correo |
| `EMAIL_ALREADY_VERIFIED` | El correo ya estaba verificado |
| `INVALID_VERIFICATION_LINK` | Enlace de verificación caducado o manipulado |
| `INVALID_RESET_TOKEN` | Token de recuperación inválido o caducado |
| `HANDLE_ROTATION_TOO_SOON` | El `postal_handle` se rotó hace menos de 30 días |
| `ACCOUNT_INACTIVE` | Cuenta desactivada o pendiente de borrado |
| `IDEMPOTENCY_IN_PROGRESS` | Ya hay una petición con la misma `Idempotency-Key` en curso |
| `RECIPIENT_NOT_FOUND` | El handle postal no existe |
| `RECIPIENT_NOT_ACCEPTING` | No acepta este tipo de carta |
| `LETTER_LOCKED` | La carta ya fue enviada, no se puede editar |
| `DRAFT_LIMIT_REACHED` | Se alcanzó el máximo de borradores abiertos |
| `ATTACHMENTS_NOT_ALLOWED` | Este tipo de carta no admite adjuntos |
| `ATTACHMENT_LIMIT_REACHED` | La carta ya tiene el máximo de adjuntos |
| `CANNOT_BLOCK_SELF` | No puedes bloquearte a ti mismo |
| `INVALID_TARGET` | El objetivo de la acción no es válido (reportarte a ti mismo, tipo no soportado…) |
| `INVALID_STATE_TRANSITION` | La operación no aplica al estado actual |
| `GRACE_PERIOD_EXPIRED` | Ya no se puede cancelar la entrega |
| `QUOTA_EXCEEDED` | Cuota diaria/semanal superada |
| `NO_RANDOM_RECIPIENT` | No hay destinatarios elegibles ahora mismo |
| `RANDOM_RESTRICTED` | El usuario tiene un `restrict_random` vigente (ADR-0004) |
| `CONTENT_FLAGGED` | Rechazado por moderación (una carta aleatoria `flagged` no es error: `202` + estado `held`) |
| `CONSENT_REQUIRED` | Falta el permiso del autor original |
| `DOLL_UNAVAILABLE` | La Doll no acepta solicitudes |
| `CHANNEL_CLOSED` | El chat ya no admite mensajes |
| `ACCOUNT_SUSPENDED` | Cuenta suspendida |

## Idempotencia

`POST /letters/{id}/send`, `POST /letters/{id}/send-random` y cualquier endpoint de pago aceptan el
header `Idempotency-Key` (UUID generado por el cliente). Repetir la misma clave en 24 h devuelve la
respuesta original sin duplicar el efecto. **La app móvil debe usarlo siempre**: la red se cae a mitad
de un POST y el usuario reintenta.

## Rate limiting

| Acción | Límite |
| --- | --- |
| General autenticado | 60/min |
| Auth (login, registro, reset) | 5/min por IP |
| Enviar carta | 30/día |
| Botella al mar | 3/día |
| Crear post | 5/hora |
| Comentar | 30/hora |
| Chat con Doll | 30/min |
| Reportar | 10/hora |

## Reglas de privacidad en las respuestas

Se aplican en el API Resource, no en la vista:

- `email` nunca aparece salvo en `GET /me`.
- Si `is_anonymous = true`, el `sender_id` y todo dato del remitente **no viajan al cliente**.
- Entregas en estado `in_transit` no aparecen en ninguna respuesta dirigida al destinatario, ni en
  conteos.
- Una entrega en estado `blocked` se le devuelve al **remitente** como `delivered`.
- 404 en lugar de 403 cuando revelar la existencia del recurso ya sería una filtración.

## Versionado desde el cliente

Todo cliente envía `X-App-Version: 1.4.2`. El backend puede responder `426` con
`{ "error_code": "UPGRADE_REQUIRED", "meta": { "min_version": "1.5.0" } }`.
