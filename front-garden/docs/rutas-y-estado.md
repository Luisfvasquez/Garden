# Rutas y estado

## Rutas

| Ruta | Vista | Guard |
| --- | --- | --- |
| `/` | Landing / feed público | – |
| `/login`, `/registro` | Auth | guest |
| `/escritorio` | Borradores, en tránsito, buzón | auth |
| `/escribir` · `/escribir/:id` | Editor | auth, verified |
| `/enviar/:id` | Flujo de envío | auth, verified |
| `/buzon` · `/buzon/:deliveryId` | Buzón y lectura | auth |
| `/envios` · `/envios/:id` | Mis envíos y seguimiento | auth |
| `/programaciones` · `/programaciones/:id` | Recurrencias y línea de tiempo | auth |
| `/botella` | Botella al mar | auth, verified |
| `/blog` · `/blog/:slug` · `/blog/nuevo` | Blog | público / auth |
| `/dolls` · `/dolls/:id` | Directorio y perfil | auth |
| `/solicitudes` · `/solicitudes/:id` | Mis solicitudes y chat | auth, participante |
| `/panel-doll` | Bandeja de la Doll | auth, role:doll |
| `/ajustes/*` | Perfil, notificaciones, privacidad, bloqueos, dispositivos | auth |

## Guards

- `auth` — sin sesión → `/login` con `redirect`.
- `verified` — email sin verificar → pantalla de verificación. **Aplica a todo lo que envía cartas.**
- `role:doll` — comprueba `has_doll_profile` de `GET /me`.
- Guard de feature flags: si el módulo está apagado en `GET /features`, la ruta redirige a `/escritorio`.

## Stores (Pinia)

| Store | Responsabilidad |
| --- | --- |
| `auth` | Usuario actual, sesión, permisos derivados |
| `letters` | Borradores, autoguardado, cola offline |
| `mailbox` | Buzón, conteo sin leer, estado abierto/cerrado |
| `deliveries` | Envíos propios y seguimiento |
| `schedules` | Programaciones y ocurrencias |
| `posts` | Feed del blog |
| `dolls` | Directorio, solicitudes, mensajes |
| `notifications` | In-app + suscripción push |
| `ui` | Tema, modales, toasts |

**Cero lógica de negocio duplicada.** Si necesitas saber si una entrega se puede cancelar, usa
`can_cancel` que viene del backend. No lo recalcules comparando fechas: el backend es la autoridad.

## Cliente HTTP

`api/client.ts` con interceptores:

- Añade `X-App-Version` y `Accept: application/json`.
- `GET /sanctum/csrf-cookie` antes del primer método mutante.
- `401` → limpiar `auth` y redirigir a login.
- `419` (CSRF expirado) → reintentar una vez tras refrescar la cookie.
- `429` → toast con `Retry-After`.
- `426` → pantalla de "actualiza la aplicación".
- Mapear siempre por `error_code`, **nunca** por el texto de `message`.

## Tipos

`src/types/api.d.ts` es **generado** desde `../docs/api/openapi.json`. No lo edites a mano.
