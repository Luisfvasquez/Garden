# ADR-0003 — Sin búsqueda abierta de usuarios

**Estado:** aceptado

## Contexto

Un buscador por nombre es lo esperable en cualquier red social. En una plataforma donde cualquiera puede
enviarte texto libre, también es un canal de acoso listo para usar.

## Decisión

**No existe `GET /users`.** El descubrimiento es solo por:

- `postal_handle` exacto (`GET /users/{handle}`), que el usuario comparte voluntariamente.
- Enlace de invitación.

El handle se puede rotar (`POST /me/postal-handle/rotate`), lo que invalida el anterior y corta el flujo
de un acosador sin bloquear uno a uno.

## Consecuencias

- El crecimiento depende de que la gente comparta su handle. Es más lento y es intencional.
- El perfil público expone lo mínimo: nombre, avatar, bio, país, mes de registro. Nada de actividad.
- Si algún día hace falta descubrimiento, debe ser opt-in explícito y con cuota, nunca por defecto.
