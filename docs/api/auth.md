# API — Autenticación y cuenta

Estado: `[ ] contrato definido` · `[ ] backend` · `[ ] front`

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
- Genera `postal_handle` automáticamente (`nombre-XXXX`, 4 hex).
- Crea `user_settings` con valores por defecto y **`accepts_random_letters = false`**.
- Envía correo de verificación. Responde `201` con el usuario, sin sesión iniciada.

## POST /auth/token (móvil)

```json
{ "email": "...", "password": "...", "device_name": "iPhone de Violet" }
```

Respuesta: `{ "data": { "token": "...", "expires_at": "..." } }`. Expiración 30 días.

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

> **No existe `GET /users` (búsqueda abierta).** El descubrimiento es solo por `postal_handle` exacto o
> enlace de invitación. Ver ADR-0003.

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

## POST /me/postal-handle/rotate

Genera un handle nuevo e invalida el anterior. Es la herramienta anti-acoso principal: corta el flujo
sin tener que bloquear uno a uno. Limitado a 1 vez cada 30 días.

## DELETE /me

Inicia el borrado. Responde `202` con `{ "data": { "deletes_at": "..." } }` (30 días). Reglas completas
en la sección 13.4 del spec: las cartas ya entregadas permanecen en el buzón ajeno con el remitente
como "Usuario eliminado".
