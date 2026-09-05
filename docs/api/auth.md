# API — Autenticación y cuenta

Estado: `[x] contrato definido` · `[x] backend` · `[~] front`

> Pendiente en backend: `GET /me/export` (RGPD asíncrono, necesita infraestructura de colas — Fase 2).
> Front: login, registro, verificación, recuperación y reset implementados. `/me` (PATCH),
> ajustes y avatar se consumen desde sus vistas en fases posteriores.

> Lee `_convenciones.md` primero.

## Endpoints

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login                    # cookie SPA
POST   /api/v1/auth/token                    # token móvil
POST   /api/v1/auth/logout
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/email/verify/{id}/{hash}
POST   /api/v1/auth/email/resend
GET    /api/v1/auth/devices
DELETE /api/v1/auth/devices/{id}

GET    /api/v1/me
PATCH  /api/v1/me
POST   /api/v1/me/avatar
GET    /api/v1/me/settings
PATCH  /api/v1/me/settings
POST   /api/v1/me/postal-handle/rotate
GET    /api/v1/me/export                     # RGPD, asíncrono
POST   /api/v1/me/deactivate
DELETE /api/v1/me                            # borrado con gracia de 30 días
GET    /api/v1/users/{postal_handle}         # perfil público mínimo
```

Rate limit: todo `/auth/*` usa el bucket `auth` (5/min por IP). El resto, el bucket general
autenticado (60/min por usuario).

## POST /auth/register

```json
{
  "name": "Violet",
  "email": "violet@example.com",
  "password": "...",
  "password_confirmation": "...",
  "birth_date": "2001-05-14",
  "timezone": "America/New_York",
  "locale": "es",
  "accepts_terms": true
}
```

- `birth_date` obligatorio. **Edad mínima 16** (o 13 con consentimiento parental según jurisdicción).
  Rechaza con `422` y `error_code: UNDER_MINIMUM_AGE`.
- `timezone` IANA válida. `locale` ∈ `es|en`. `accepts_terms` debe ser `true`.
- `password` pasa `Password::defaults()` (en producción, además `uncompromised()`).
- Genera `postal_handle` automáticamente (`nombre-XXXX`, 4 hex).
- Crea `user_settings` con valores por defecto y **`accepts_random_letters = false`**.
- Envía correo de verificación. Responde `201` con el recurso `me` (sin sesión iniciada,
  `email_verified: false`).

## POST /auth/login — cookie SPA

Precede con `GET /sanctum/csrf-cookie`.

```json
{ "email": "...", "password": "...", "remember": true }
```

- `204` sin cuerpo y cookie de sesión al validar.
- `422 INVALID_CREDENTIALS` si email/contraseña no casan (mismo error para ambos, no revela cuál).
- `403 ACCOUNT_INACTIVE` si `status ∈ {suspended, deactivated, deleted}`.
- No exige email verificado para iniciar sesión; sí lo exigen los endpoints de escritura sensibles.

## POST /auth/token — token móvil

```json
{ "email": "...", "password": "...", "device_name": "iPhone de Violet" }
```

- Respuesta `201`: `{ "data": { "token": "...", "expires_at": "2026-10-05T10:00:00Z" } }`. Expiración 30 días.
- Mismos errores que `login`.
- `device_name` obligatorio; identifica la entrada en `GET /auth/devices`.

## POST /auth/logout

- Sesión SPA: invalida la sesión y regenera el token CSRF. `204`.
- Token móvil: revoca **el token en uso**. `204`.

## POST /auth/forgot-password

```json
{ "email": "..." }
```

Siempre `202` aunque el email no exista (no revela el padrón). Envía el enlace de reseteo si procede.

## POST /auth/reset-password

```json
{ "token": "...", "email": "...", "password": "...", "password_confirmation": "..." }
```

- `204` al restablecer. Revoca todos los tokens personales y sesiones del usuario.
- `422 INVALID_RESET_TOKEN` si el token es inválido o caducó.

## POST /auth/email/verify/{id}/{hash}

`id` = uuid del usuario, `hash` = sha1 del email. Requiere query `expires` y `signature`
(URL firmada temporal generada en el correo).

- `204` al verificar (idempotente-ish: ver siguiente punto).
- `409 EMAIL_ALREADY_VERIFIED` si ya estaba verificado.
- `403 INVALID_VERIFICATION_LINK` si la firma no valida o caducó.
- No requiere estar autenticado (la firma es la credencial).

## POST /auth/email/resend

Autenticado. Reenvía el correo de verificación al usuario actual.

- `202` si se encoló el envío.
- `409 EMAIL_ALREADY_VERIFIED` si ya estaba verificado.

## GET /auth/devices

Lista los tokens personales (sesiones móviles) del usuario actual.

```json
{
  "data": [
    {
      "id": "01J8...",
      "device_name": "iPhone de Violet",
      "last_used_at": "2026-09-01T18:22:00Z",
      "created_at": "2026-08-14T10:00:00Z",
      "current": true
    }
  ]
}
```

`current` sólo puede ser `true` cuando la petición llega con ese mismo token.

## DELETE /auth/devices/{id}

Revoca ese token. `204`. `404` si el token no es del usuario (no revela existencia ajena).

## GET /me

```json
{
  "data": {
    "id": "01J8...",
    "name": "Violet",
    "pen_name": null,
    "email": "violet@example.com",
    "postal_handle": "violet-e4f2",
    "avatar_url": null,
    "bio": null,
    "role": "client",
    "status": "active",
    "email_verified": true,
    "country_code": "US",
    "timezone": "America/New_York",
    "locale": "es",
    "accepts_random_letters": false,
    "has_doll_profile": false,
    "unread_mailbox_count": 2,
    "created_at": "2026-01-14T10:00:00Z"
  }
}
```

> `has_doll_profile` y `unread_mailbox_count` dependen de módulos posteriores (Dolls, Buzón).
> Hasta que existan, valen `false` y `0`.

## PATCH /me

Campos opcionales, todos individualmente: `name`, `pen_name` (o `null` para quitarlo),
`bio` (o `null`), `country_code` (ISO-3166-1 alpha-2), `timezone` (IANA), `locale` (`es|en`).
Devuelve el recurso `me` completo. `pen_name` es único: `422` si colisiona.

## POST /me/avatar

`multipart/form-data`, campo `avatar`. Imagen `jpg|png|webp`, máx 5 MB, lado máx 2048 px.
Devuelve el recurso `me` con `avatar_url` poblado. Sustituye y borra el avatar anterior.

## GET /me/settings

```json
{
  "data": {
    "notify_email": true,
    "notify_push": true,
    "notify_on_arrival": true,
    "notify_on_dispatch_confirm": false,
    "notify_on_doll_message": true,
    "notify_on_blog_comment": true,
    "share_read_receipts": false,
    "quiet_hours_start": "23:00",
    "quiet_hours_end": "07:30",
    "theme": "system",
    "preferred_paper_style": null,
    "show_transit_countdown": true,
    "accepts_random_letters": false,
    "random_letters_daily_cap": 3
  }
}
```

`accepts_random_letters` y `random_letters_daily_cap` viven en `users` (no en `user_settings`)
pero se exponen aquí por comodidad del cliente.

## PATCH /me/settings

```json
{
  "notify_email": true,
  "notify_push": true,
  "notify_on_arrival": true,
  "notify_on_dispatch_confirm": false,
  "notify_on_doll_message": true,
  "notify_on_blog_comment": true,
  "share_read_receipts": false,
  "quiet_hours_start": "23:00",
  "quiet_hours_end": "07:30",
  "theme": "system",
  "accepts_random_letters": false,
  "random_letters_daily_cap": 3,
  "show_transit_countdown": true
}
```

Todos los campos opcionales. `quiet_hours_*` en formato `HH:MM` o `null` (los dos juntos).
`random_letters_daily_cap` ∈ `0..10`. Devuelve el recurso de `GET /me/settings`.

## POST /me/postal-handle/rotate

Genera un handle nuevo e invalida el anterior. Es la herramienta anti-acoso principal: corta el flujo
sin tener que bloquear uno a uno. Limitado a 1 vez cada 30 días.

- `200` con el recurso `me` (nuevo `postal_handle`).
- `429 HANDLE_ROTATION_TOO_SOON` con `meta.retry_at` si aún no han pasado 30 días.

## POST /me/deactivate

Desactivación reversible. `status → deactivated`, `deactivated_at = now()`. Revoca tokens y sesiones.
`204`. Volver a iniciar sesión reactiva la cuenta (`status → active`).

## DELETE /me

Inicia el borrado. Responde `202` con `{ "data": { "deletes_at": "..." } }` (30 días). Reglas completas
en la sección 13.4 del spec: las cartas ya entregadas permanecen en el buzón ajeno con el remitente
como "Usuario eliminado".

## GET /users/{postal_handle} — perfil público

**Solo** estos campos. Nada de actividad, ni conteos, ni email:

```json
{
  "data": {
    "postal_handle": "hana-8c21",
    "display_name": "Hana",
    "avatar_url": "...",
    "bio": "...",
    "country_code": "JP",
    "member_since": "2026-02",
    "accepts_random_letters": true,
    "is_blocked_by_me": false
  }
}
```

- `display_name` = `pen_name` si existe, si no `name`.
- `is_blocked_by_me` depende del módulo de bloqueos (Fase 1); hasta entonces siempre `false`.
- `404` si el handle no existe o el usuario no está `active` (no revela cuentas suspendidas/borradas).
- Requiere autenticación.

> **No existe `GET /users` (búsqueda abierta).** El descubrimiento es solo por `postal_handle` exacto o
> enlace de invitación. Ver ADR-0003.
