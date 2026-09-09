# Progreso del proyecto

**Actualizar al cerrar cada tarea.** Este archivo es lo que le dice al agente qué existe ya.
Formato: `[ ]` pendiente · `[~]` en curso · `[x]` terminado.

Última actualización: 2026-09-09 — **Fase 1 completa** (backend + front): dos personas pueden escribirse cartas que tardan en llegar, incluso sin conexión al redactar. Siguiente: checklist de lanzamiento / Fase 2.

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
- [x] Migraciones `letters`, `letter_attachments`, `letter_deliveries`, `delivery_events` (+ índices parciales)
      _(+ `transit_routes`, `postal_holidays`; índices parciales en migración propia — ADR-0006; `letters.in_reply_to_delivery_id` añadido tras `deliveries`)_
- [x] Enums (`DeliveryStatus`, `LetterKind`, `TransitTier`, ...)
      _(+ `DeliveryMode`, `DeliveryEventType`, `ModerationStatus`, `AttachmentType`; `DeliveryStatus::asSeenBySender()` traduce `blocked → delivered`)_
- [x] CRUD de cartas + autoguardado + sanitización + cifrado de `body`
      _(`LetterController` apiResource + `/preview`; Tiptap sanitizado a lista blanca; `body` `encrypted:array` + `body_plain`; límites 20k car. / 50 borradores; `LetterPolicy` → 404 sin fuga de existencia; máquina de estados en `LetterDelivery` con `mark*()`/`cancel()` atómicos + `delivery_events`)_
- [x] `GET /letters/styles` (catálogo)
      _(config `letter_styles.php`, cacheable, `locked:false`)_
- [x] Adjuntos con límites
      _(`POST/DELETE /letters/{id}/attachments`; 3/carta, imagen jpg/png/webp ≤5 MB con `width`/`height`, audio con `duration_seconds` del cliente; no en aleatorias (`ATTACHMENTS_NOT_ALLOWED`) ni en enviadas (`LETTER_LOCKED`); binding con scope al `letter`)_
- [x] `POST /letters/{id}/send` con `Idempotency-Key`
      _(`LetterSender` + `SendOptions`; una entrega por destinatario; `scheduled_for` hacia atrás; middleware `verified` + `throttle:send-letter` (30/día) + `idempotency` (replay 24 h, `IDEMPOTENCY_IN_PROGRESS`); cuota exprés 3/mes → `QUOTA_EXCEEDED`; `mode:random` diferido a Fase 2)_
- [x] `TransitCalculator` (tiers, factor geográfico, jitter, mínimo 30 min)
      _(`config/postal.php`; `scheduledFor()` / `estimateMinutes()` / `provisionalEstimate()` / `dispatchPlan()` → DTO `DispatchPlan`)_
- [x] `DispatchDueLettersJob` con reserva por lote (idempotente)
      _(reserva atómica con `dispatch_batch_id`, `ShouldBeUnique`, cola `postal`)_
- [x] `DispatchSingleLetterJob`
      _(idempotente: sale si ya no está `queued`; orden de comprobaciones de `jobs-y-colas.md`; `tries=3` `backoff=[60,300,900]`; `failed()` marca `failed` si quedó colgada)_
- [x] `DeliverArrivedLettersJob`
      _(`cursor()` + `markDelivered()` guardado; `ShouldBeUnique`)_
- [~] Horizon + colas separadas + scheduler
      _(scheduler en `routes/console.php` cada minuto con `withoutOverlapping`; jobs en cola `postal`. Horizon (dashboard/supervisor + Redis) diferido como docker)_
- [x] `delivery_events` + endpoint `/tracking` con oficinas ficticias
      _(`GET /deliveries/{id}/tracking` → `TrackingEventResource` con etiquetas ES; `TransitRoute` + seeder + `PostalRoutes`; el despacho registra `sorting_office` repartidos por la ventana de tránsito y la entrega `out_for_delivery`; un `blocked` nunca aparece en el timeline)_
- [x] Buzón: listar (sobre cerrado), abrir, archivar, favorito, responder, unread-count
      _(`MailboxController`; `scopeInMailbox` = solo `delivered`/`read`, nunca `in_transit` ni en conteos; `open` → carta completa + `markRead` idempotente + evento `LetterRead` si ambas partes lo permiten; `reply` crea borrador con `in_reply_to_delivery_id` sin enviar nada; anonimato con `reveal_sender_at`; `?status=` y `?updated_since=`)_
- [x] Cancelación con ventana de gracia
      _(`POST /deliveries/{id}/cancel` → `LetterDelivery::cancel()`; `GRACE_PERIOD_EXPIRED` / `INVALID_STATE_TRANSITION` 409)_
- [x] Bloqueos (`blocks`) + traducción `blocked → delivered` para el remitente
      _(`GET/POST/DELETE /blocks` por `user_id` o `postal_handle`, `updateOrCreate` idempotente, silencioso; el despacho ya consume `Block::exists()` → `markBlocked()`; `DeliveryStatus::asSeenBySender()` traduce en `DeliveryResource` + filtro `?status=delivered`)_
- [x] Reportes (`reports`) básicos
      _(`POST /reports` polimórfico: `letter_delivery`/`user` cableados, resto → `INVALID_TARGET`; severidad automática por `ReportCategory::severity()`; `minor_safety`/`self_harm` → `critical` + `Log::critical`; `throttle:report` 10/h; un reporte por (reportante, objetivo))_
- [x] Notificaciones in-app + email (llegada, entrega, fallo)
      _(eventos `LetterDispatched`/`LetterDelivered`/`LetterFailed`/`LetterRead` → listeners → `Notification` base `LetterNotification` (canales `database` + `mail` según `user_settings`, `List-Unsubscribe`, `isEnabled()` por tipo); llegada anónima nunca revela remitente; `notify_on_dispatch_confirm` cubre despacho+entrega; endpoints `GET /notifications` + `/unread-count` + `/{id}/read` + `/read-all`; tabla `notifications` con `uuidMorphs`)_
      _(Push, `quiet_hours` y agrupación → Fase 2)_
- [~] Tests: máquina de estados completa, policies, `travel()` sobre programación
      _(máquina de estados, reloj postal end-to-end con `travelTo()`, policies de aislamiento sender/recipient, buzón y tracking cubiertos. `travel()` sobre programaciones llega en Fase 2)_

### Front
- [~] Editor de carta (Tiptap restringido) con autoguardado
      _(`LetterEditor` StarterKit v3 restringido a negrita/cursiva/subrayado/cita/regla; `useAutosave` con debounce 5 s + estado guardando/guardado/error + «guardar ahora»; `EMPTY_DOC`. **Pendiente: escritura previa a IndexedDB antes de la red — va con el chunk PWA/offline**)_
- [x] Selector de estética + vista previa a página completa
      _(`StylePicker` alimentado por `GET /letters/styles` vía TanStack Query; `LetterPaper` renderiza el JSON de Tiptap con `generateHTML` + estilo resuelto; toggle editor↔preview)_
- [x] Flujo de envío (handle, tier, fecha de llegada, anonimato)
      _(`SendView`: lookup de destinatario por `postal_handle` con debounce, radios de tier, llegada programada opcional, anónimo + revelar-después, acuse de lectura; `Idempotency-Key` por instancia de formulario; errores por `error_code`)_
- [~] Buzón con sobres cerrados y animación de apertura
      _(`MailboxView` con filtros; `EnvelopeClosed` (papel + `WaxSeal`); `MailboxReadView` con rotura de lacre (respeta `prefers-reduced-motion`) → `LetterPaper`; archivar/favorito/responder. Pendiente: visor de adjuntos, paginación cursor, pulido de animación)_
- [x] Vista de lectura de carta
      _(`LetterPaper` compartido con la preview; anonimato lo resuelve el backend)_
- [x] Mis envíos + pantalla de seguimiento postal
      _(`OutboxView` con filtros de estado; `DeliveryTrackingView` con timeline de `delivery_events` (etiquetas del backend) + cancelación dentro de la ventana de gracia)_
- [x] Ajustes: perfil, notificaciones, privacidad, bloqueos, dispositivos
      _(`SettingsView` con 6 secciones: `ProfileSection` (`PATCH /me` + avatar `POST /me/avatar`), `NotificationsSection` (`GET/PATCH /me/settings` + tema → `ui.setTheme`), `PrivacySection` (aleatorias/cap/acuses/countdown), `BlocksSection` (`/blocks` por handle, `user.id` para desbloquear), `DevicesSection` (`GET/DELETE /auth/devices`, no revoca el actual), `AccountSection` (rotar handle, desactivar, borrar → logout). `api/{account,devices,blocks}.ts` + `composables/useAccount.ts`. `auth.setUser()` adopta el `me` fresco)_
- [x] PWA instalable: manifest, service worker, caché offline del buzón
      _(iconos 192/512/maskable generados por `scripts/make-icons.mjs`; `runtimeCaching` de Workbox: `GET /api/v1/mailbox*` `StaleWhileRevalidate`, imágenes/fuentes `CacheFirst`; `PwaPrompt` con `virtual:pwa-register/vue` para «nueva versión» / «listo sin conexión»)_
- [x] Cola offline de borradores en IndexedDB
      _(Dexie `evergarden.drafts`; `useAutosave` escribe a IndexedDB **antes** de la red → estado `pending` si no hay conexión; `useDraftSync` reintenta al volver online / cada 30 s (`App.vue`); `EditorView` recupera el borrador local no sincronizado sobre la copia del servidor; conflicto → gana el local en el flush)_

> Capa de datos: `@tanstack/vue-query` (`main.ts` + `qk` en `api/queryKeys.ts`), módulos `api/{letters,deliveries,mailbox,users}.ts`, composables `use{Letters,Deliveries,Mailbox}` con invalidación. `queryClient.clear()` al cerrar sesión.
> Desviación anotada: `src/types/api.ts` sigue escrito a mano (crece con Fase 1) hasta Scramble. `LetterPaper` arrastra ~374 KB (prosemirror de `generateHTML`) pero se carga en lazy solo en preview/lectura.

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
