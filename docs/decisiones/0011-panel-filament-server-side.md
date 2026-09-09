# ADR-0011 — El panel de moderación es server-side (Filament), no consume la API

**Estado:** aceptado

## Contexto

`backend-garden` es una API REST desacoplada: "no renderiza vistas" (`backend-garden/CLAUDE.md`).
Pero la capa **humana** de moderación (`docs/moderacion.md`) necesita una cola priorizada por
`severity`, revisión de contenido retenido, gestión de usuarios y métricas operativas. Construir todo
eso como vistas en la PWA sería semanas de trabajo para una herramienta que solo usa el equipo, y
mezclaría un rol de alto privilegio con el cliente público.

## Decisión

- El panel es **Filament v4** montado en `/admin`, renderizado en el servidor (Livewire/Blade). Es la
  **única** excepción consciente a "la API no renderiza vistas": el panel **no** pasa por `/api/v1`,
  no es un cliente más, y su contrato no vive en `docs/api/`.
- **Autenticación por sesión `web`** (guard propio de Filament), no Sanctum. `User::canAccessPanel()`
  exige `isStaff()` (rol `moderator` o `admin`) **y** estado `active`.
- Las acciones del panel reutilizan la **lógica de dominio ya existente**, nunca la reimplementan:
  - Confirmar un reporte → `Report::markActioned()` (que dispara `RandomAbuseGuard` → `restrict_random`
    automático, ADR-0004).
  - Aprobar/rechazar un post → `PublicPost::publishIfReady()` / `moderation_status`.
  - Restringir aleatorias → crea un `moderation_action` igual que el flujo automático.
- Recursos: usuarios (suspender / restringir aleatorias), cola de reportes (orden por severidad),
  moderación del blog (posts retenidos), catálogos (`feature_flags`, `support_resources`, `tags`) y un
  widget de métricas (usuarios, tránsito, **salud del reloj postal**, moderación pendiente).

## Consecuencias

- Filament (y Livewire) son dependencias pesadas nuevas, justificadas por el ahorro frente a construir
  el back-office a mano y por ser el estándar del ecosistema Laravel. No se cargan en las rutas de la
  API.
- El panel comparte modelos y base de datos con la API; cualquier `mark*()` / método de dominio nuevo
  debe seguir siendo la vía única de cambio de estado, para que panel y API no divergan.
- Cola separada `web` + CSRF de sesión: el panel necesita `EncryptCookies` + `StartSession`, que la API
  stateless no usa. Están aislados en el middleware stack de `AdminPanelProvider`.
- Pendiente (no bloquea): recursos de `transit_routes` / `postal_holidays`, cola dedicada de
  comentarios retenidos (hoy se ven filtrando la de posts), y el escalado real email/Slack de reportes
  críticos.
