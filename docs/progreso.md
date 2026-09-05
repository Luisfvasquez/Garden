# Progreso del proyecto

**Actualizar al cerrar cada tarea.** Este archivo es lo que le dice al agente qué existe ya.
Formato: `[ ]` pendiente · `[~]` en curso · `[x]` terminado.

Última actualización: 2026-09-05 — Fase 0 completa: backend (auth + cuenta) y front (cimientos + vistas de auth).

---

## Fase 0 — Cimientos

### Backend
- [ ] Repo, docker-compose (php, nginx, pgsql, redis, horizon, scheduler, reverb, mailpit, minio)
      _(diferido: el entorno Laragon local —pgsql 17, redis— ya funciona; la contenerización es tarea aparte)_
- [x] Laravel 11 skeleton API + `install:api` _(Laravel 13 en el entorno; `routes/api.php` → manifiesto de versiones → `routes/api/v1.php`)_
- [x] Sanctum configurado (cookie SPA + tokens móviles) y CORS
      _(`statefulApi`, `config/cors.php` orígenes explícitos + credenciales, token móvil 30d, `PersonalAccessToken` UUID; `login` cookie + `token` móvil implementados)_
- [x] Estructura `/api/v1` con `_convenciones.md` aplicada (Resources, FormRequests, error handler)
      _(envelope único `error_code` + `meta.request_id`, `X-Request-Id`, versionado, un FormRequest y un API Resource por acción; fechas ISO-8601 `Z` globales vía `Date::serializeUsing`)_
- [x] Pint + Larastan 6 + Pest en CI
      _(en verde local + `.github/workflows/backend-ci.yml`: Pint `--test`, PHPStan nivel 6, Pest sobre servicio Postgres 17)_
- [x] Migración `users` + `user_settings` + generación de `postal_handle`
      _(+ `feature_flags`, `personal_access_tokens` UUID; `postal_handle` `nombre-XXXX` hex en `User::creating`)_
- [x] Auth: registro (con verificación de edad → `UNDER_MINIMUM_AGE`), login, logout, verificación email (URL firmada relativa), reset (revoca tokens), reenvío, dispositivos
- [x] `GET /me`, `PATCH /me`, ajustes (`GET/PATCH /me/settings`), avatar; + `deactivate`, `DELETE /me` (gracia 30d), `postal-handle/rotate` (cooldown 30d)
- [x] `GET /users/{handle}` (perfil público mínimo, solo `active`, sin email)
- [~] Policies base + tests de aislamiento entre usuarios
      _(aislamiento probado: dispositivos/ajustes/perfil sólo accesibles con el token propio, revocar dispositivo ajeno → 404. Las Policies de dominio (`LetterPolicy`…) nacen con su módulo, spec §5.3)_
- [x] `GET /health`, `GET /features` (feature flags)

### Front
- [x] Vite + Vue 3 + TS + Tailwind + Pinia + Router con guards
      _(scaffold create-vue ya existía; Tailwind v4 por `@theme` en `assets/main.css`, router con `beforeEach` → `guards.ts` (guest/auth/verified/role))_
- [x] `api/client.ts` con interceptores (auth, 401, 419, 429, error_code)
      _(Axios `withCredentials` + XSRF, pre-flight CSRF en mutaciones, reintento único en 419, `ApiError` normalizado por `error_code`, hooks 401/429/426 conectados en `main.ts`)_
- [x] Sistema de diseño base: tokens, tipografía, paleta, componentes primitivos
      _(tokens de `sistema-diseno.md` como CSS vars + `@theme`; modo oscuro «madera y lámpara» por clase `.dark`; `prefers-reduced-motion`; primitivos `BaseButton/BaseInput/BaseCheckbox/AlertBox/SpinnerDots/ToastHost/ThemeToggle/LocaleSwitch`)_
- [x] Vistas de auth: registro, login, verificar email, recuperar contraseña
      _(+ reset con token; los tres estados carga/error/vacío; errores por `error_code`; edad → `UNDER_MINIMUM_AGE` bajo el campo)_
- [x] Layout autenticado + navegación
      _(`AppLayout` con nav + menú de usuario + logout; `AuthLayout` centrado; `DeskView`/`SettingsView` placeholder)_
- [x] i18n es/en con las claves de auth
      _(`vue-i18n` legacy:false, `locales/es.json` + `en.json`, persistencia de idioma y tema en `localStorage` (solo prefs de UI))_
- [~] PWA instalable
      _(plugin configurado, `npm run build` genera SW; faltan iconos en `public/icons/` — se completa en Fase 1)_

> Desviación consciente: `src/types/api.ts` es un stand-in **escrito a mano y mínimo** (solo sesión)
> hasta que el backend exponga `openapi.json` con Scramble (Fase 4). `npm run api:types` sigue siendo
> el recordatorio. Front CI en `.github/workflows/front-ci.yml`.

---

## Fase 1 — El correo funciona (MVP)

> **Criterio de lanzamiento:** dos personas pueden escribirse cartas que tardan en llegar.
> Eso es el producto. Todo lo demás es ampliación.

### Backend
- [ ] Migraciones `letters`, `letter_attachments`, `letter_deliveries`, `delivery_events` (+ índices parciales)
- [ ] Enums (`DeliveryStatus`, `LetterKind`, `TransitTier`, ...)
- [ ] CRUD de cartas + autoguardado + sanitización + cifrado de `body`
- [ ] `GET /letters/styles` (catálogo)
- [ ] Adjuntos con límites
- [ ] `POST /letters/{id}/send` con `Idempotency-Key`
- [ ] `TransitCalculator` (tiers, factor geográfico, jitter, mínimo 30 min)
- [ ] `DispatchDueLettersJob` con reserva por lote (idempotente)
- [ ] `DispatchSingleLetterJob`
- [ ] `DeliverArrivedLettersJob`
- [ ] Horizon + colas separadas + scheduler
- [ ] `delivery_events` + endpoint `/tracking` con oficinas ficticias
- [ ] Buzón: listar (sobre cerrado), abrir, archivar, favorito, responder, unread-count
- [ ] Cancelación con ventana de gracia
- [ ] Bloqueos (`blocks`) + traducción `blocked → delivered` para el remitente
- [ ] Reportes (`reports`) básicos
- [ ] Notificaciones in-app + email (llegada, entrega, fallo)
- [ ] Tests: máquina de estados completa, policies, `travel()` sobre programación

### Front
- [ ] Editor de carta (Tiptap restringido) con autoguardado
- [ ] Selector de estética + vista previa a página completa
- [ ] Flujo de envío (handle, tier, fecha de llegada, anonimato)
- [ ] Buzón con sobres cerrados y animación de apertura
- [ ] Vista de lectura de carta
- [ ] Mis envíos + pantalla de seguimiento postal
- [ ] Ajustes: perfil, notificaciones, privacidad, bloqueos, dispositivos
- [ ] PWA instalable: manifest, service worker, caché offline del buzón
- [ ] Cola offline de borradores en IndexedDB

---

## Fase 2 — Tiempo y comunidad

### Backend
- [ ] `letter_schedules` + `OccurrenceGenerator` + `GenerateUpcomingDeliveriesJob`
- [ ] Endpoints de schedules y ocurrencias (incluye `leap_day_policy`)
- [ ] `public_posts`, `comments`, `reactions`, `tags`
- [ ] Flujo de consentimiento para publicar cartas recibidas
- [ ] Botella al mar: `RandomRecipientPicker` + pool en Redis + cuotas
- [ ] `ContentModerator` (interfaz + driver local) + jobs de moderación
- [ ] Panel Filament: usuarios, cola de reportes, catálogos, métricas
- [ ] Web Push (VAPID) + `quiet_hours` + agrupación
- [ ] `support_resources` + endpoint

### Front
- [ ] Vista de programaciones + línea de tiempo de ocurrencias
- [ ] Blog: feed, post, publicar, comentar, reaccionar
- [ ] Flujo de solicitud y respuesta de consentimiento
- [ ] Botella al mar (envío + cuota + respuesta anónima única)
- [ ] Suscripción a push + gestión de permisos
- [ ] i18n completo es/en

---

## Fase 3 — Auto Memory Dolls

### Backend
- [ ] `doll_profiles` + solicitud y verificación del rol
- [ ] Directorio con filtros
- [ ] `doll_requests` con máquina de estados + `ExpireStaleDollRequestsJob`
- [ ] Reverb + `routes/channels.php` + doble validación en controlador
- [ ] `doll_chat_messages` + borradores versionados + aprobación
- [ ] Filtro anti-intercambio de contactos
- [ ] Valoraciones + `RecalculateDollRatingsJob`
- [ ] `PurgeOldDollChatsJob` (90 días)
- [ ] (Opcional) Stripe Connect + pagos retenidos

### Front
- [ ] Directorio de Dolls + perfil
- [ ] Crear solicitud con brief
- [ ] Chat en tiempo real (Echo) + indicador de escritura
- [ ] Visor de borradores versionados + aprobación
- [ ] Panel de la Doll (bandeja de solicitudes)
- [ ] Valoración post-servicio

---

## Fase 4 — Móvil y refinamiento

- [ ] Scramble → `openapi.json` → tipos TS generados
- [ ] Capacitor sobre la PWA + push nativo (FCM/APNs)
- [ ] Sincronización delta (`updated_since`)
- [ ] Exportación de cartas a PDF
- [ ] Búsqueda con Meilisearch
- [ ] Cartas póstumas por inactividad (con aviso legal)
- [ ] Particionado de `letter_deliveries` si el volumen lo pide

---

## Checklist previo al lanzamiento

- [ ] Verificación de email obligatoria activa
- [ ] Edad mínima verificada en el registro
- [ ] Cartas aleatorias en opt-in explícito
- [ ] Filtro de moderación activo en aleatorias y blog
- [ ] Bloqueo y reporte accesibles desde cada carta, post y perfil
- [ ] Recursos de ayuda configurados para los países principales
- [ ] Términos y política de privacidad publicados
- [ ] Backups automáticos con restauración probada
- [ ] Alertas de Horizon y del "reloj postal"
- [ ] Tests de policy al 100 %
- [ ] Rate limits en todos los endpoints de escritura
- [ ] `APP_DEBUG=false`, `/docs` protegido, Telescope desactivado
- [ ] Lighthouse: PWA instalable y funcional sin conexión
