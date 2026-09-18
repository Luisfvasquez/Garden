# Progreso del proyecto

**Actualizar al cerrar cada tarea.** Este archivo es lo que le dice al agente qué existe ya.
Formato: `[ ]` pendiente · `[~]` en curso · `[x]` terminado.

Última actualización: 2026-09-18 — **Fases 1, 2 y 3 completas.** Fase 4 en curso: **4A** Scramble → `docs/api/openapi.json` → tipos TS (con la desviación de ADR-0014: los tipos del front siguen a mano porque los generados son más débiles, y `src/types/contract.ts` impide que se desvíen) y **4B** sincronización delta (`?updated_since=` con marca de agua del servidor, ventana `>`, orden por `updated_at` y lápidas para borrados). **Pendiente de Fase 4:** Capacitor + push nativo, exportación a PDF, búsqueda con Meilisearch, cartas póstumas y particionado de `letter_deliveries` — los cuatro primeros requieren dependencias o infraestructura nuevas, así que se preguntan antes (CLAUDE.md). **Diferido a propósito:** pagos con Stripe (ADR-0012), `ModerateContentJob` asíncrono, Horizon y la contenerización.

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

> ~~Desviación consciente: `src/types/api.ts` es un stand-in escrito a mano hasta Scramble (Fase 4).~~
> **Resuelto en 4A, aunque no como se había previsto:** los tipos generados resultaron ser *más débiles*
> que los escritos a mano (Scramble sólo puede decir `string` donde hay uniones literales), así que
> `api.ts` se queda a mano a propósito y `src/types/contract.ts` vigila en cada `typecheck` que no se
> desvíe del OpenAPI generado. Ver **ADR-0014**. Front CI en `.github/workflows/front-ci.yml`.

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
- [x] `letter_schedules` + `OccurrenceGenerator` + `GenerateUpcomingDeliveriesJob`
      _(2B: migraciones `letter_schedules` + `letter_schedule_occurrences` + FK `schedule_id` en `letter_deliveries`; `OccurrenceGenerator` puro (yearly/monthly/weekly/once/custom_dates, `leap_day_policy` vía método de Carbon, recorte mensual, DST — ADR-0009); `OccurrenceMaterializer` (idempotente, `scheduled_for` hacia atrás desde `runs_at`); `GenerateUpcomingDeliveriesJob` diario 03:00 cola `maintenance`, 90 días (ADR-0002), `ShouldBeUnique`. `evergarden:seed-demo` con ocurrencias: ver comando de seeds más abajo)_
- [x] Endpoints de schedules y ocurrencias (incluye `leap_day_policy`)
      _(9 endpoints; `feature:schedules` (404 si flag off) + `verified` en `store`; `SchedulePolicy` 404 sin fuga; `OccurrenceTimeline` mezcla materializadas + virtuales con `meta.total/filled/delivered`; `PUT .../occurrences/{date}/letter` con 404/409; middleware `feature` nuevo y reutilizable para 2C/2D)_
- [x] `public_posts`, `comments`, `reactions`, `tags`
      _(2D: migraciones `tags`/`taggables`/`public_posts` (`tsvector`+GIN, cuerpo sin cifrar)/`comments` (1 nivel, FK self tras crear)/`reactions`; enums `PostType`/`ReactionType`/`ConsentStatus`/`PostVisibility`; `BlogPublisher` (filtro síncrono: rejected→422, flagged→202 invisible), `PublicPost`/`Comment`/`Reaction`/`Tag` + policies 404; endpoints públicos `GET /posts`,`/posts/{slug}`,`/posts/{id}/comments`,`/tags` + escritura tras `verified` + `throttle:create-post`/`comment`; reacciones sin conteos públicos; `ReportableType` cablea `public_post`/`comment`)_
- [x] Flujo de consentimiento para publicar cartas recibidas
      _(`shared_letter` → `consent_status=pending`, 202, fuera del feed; `GET /consent-requests` (posts que esperan mi permiso, con vista previa exacta anonimizada), `POST /consent-requests/{post}/respond` (`granted` → publica; `denied` → veto 90 días para esa carta → `403 CONSENT_REQUIRED`); `POST /posts/{id}/request-consent` idempotente. Notificación real al autor → 2E)_
- [x] Botella al mar: `RandomRecipientPicker` + pool en Redis + cuotas
      _(2C: `RandomLetterSender` (gates en orden, filtro síncrono), `RandomRecipientPool` interfaz (Redis `predis` / array en tests — ADR-0010) + `RandomRecipientPicker` (`SRANDMEMBER` + verificación en BD) + `RefreshRandomRecipientPoolJob` (15 min); `DispatchSingleLetterJob` resuelve destinatario al despachar, `no_recipient` → borradores; `POST /letters/{id}/send-random` (feature+verified+throttle+idempotency), `GET /random/quota`, `POST /mailbox/{id}/reply-anonymous` (única), `POST /mailbox/{id}/open-correspondence` (doble → revela handles); `moderation_actions` + `RandomAbuseGuard` (2 reportes `actioned` → `restrict_random`); bloqueo cancela aleatorias pendientes; `held` estado nuevo)_
- [~] `ContentModerator` (interfaz + driver local) + jobs de moderación
      _(2A: `ContentModerator` + `ModerationContext`/`ModerationVerdict` + enums + `LocalModerator` (léxico/regex de `config/moderation.php`) + `PiiScanner`, `MODERATION_DRIVER` → `ModerationServiceProvider`; ADR-0008. `self_harm`→`flagged` nunca `rejected`; `minor_safety`→`rejected`; PII bloquea en aleatorias, avisa en Doll chat. 2C: `moderation_actions` + `RandomAbuseGuard` (2 reportes `actioned` → `restrict_random`) + moderación síncrona en botella. **Cierre:** `CriticalAlertDispatcher` (log siempre + correo real a `MODERATION_ALERT_EMAIL` cuando la categoría es crítica; nunca revienta la petición si el mailer falla) cableado en `ReportController`, `RandomLetterSender` (carta y respuesta aleatoria retenidas) y `BlogPublisher` (post y comentario retenidos) — política única en `alertIfCritical()`. **Sigue pendiente, a propósito:** `ModerateContentJob` asíncrono — `LocalModerator` responde en microsegundos, así que la moderación síncrona ya cumple el contrato sin la complejidad de una cola; se implementa si se añade un driver alojado más lento)_
- [x] Panel Filament: usuarios, cola de reportes, catálogos, métricas
      _(2F: Filament v4 en `/admin` server-side (sesión `web`, `canAccessPanel` = `isStaff()` + `active`; ADR-0011). `UserResource` (suspender/reactivar/restringir aleatorias/levantar, sin crear), `ReportResource` (cola ordenada por severidad vía `array_position`, «Confirmar» → `Report::markActioned()` dispara `RandomAbuseGuard`, «Descartar»), `PublicPostResource` (moderación blog, filtro `flagged` por defecto, aprobar → `publishIfReady` / rechazar), catálogos `FeatureFlag`/`SupportResource`/`Tag`, `PlatformStatsWidget` (usuarios, tránsito, salud del reloj postal, moderación pendiente). Tests de acceso: guest→login, client→403, moderador suspendido→403, moderador activo/admin→render de las páginas)_
- [x] Web Push (VAPID) + `quiet_hours` + agrupación
      _(2E: `minishlink/web-push`; `push_subscriptions` + `POST/DELETE /push-subscriptions` + `GET /push/vapid-public-key` (público); `WebPushChannel` (clase) inserta en `scheduled_pushes` con `deliver_after` = fin de `quiet_hours` local (cruza medianoche) o ahora; `DispatchDuePushesJob` cada minuto agrupa por `(user,type)` → 1 push y poda suscripciones caídas; `WebPushClient` interfaz (Minishlink/Null/doble de test); llegada anónima no nombra al remitente; `toWebPush()` en arrived/delivered/failed. Front: `usePush` (permiso + `pushManager.subscribe` + VAPID key), `PushSection` en Ajustes, `public/push-sw.js` (`push`/`notificationclick`) vía `workbox.importScripts`, i18n)_
- [x] `support_resources` + endpoint
      _(migración + modelo + `SupportResourceSeeder` (024/Esperanza ES, Línea de la Vida MX, CAS AR, 988 US, Befrienders global); `GET /support-resources?country_code=&topic=` público → país + fallback internacional por `priority`)_
- [x] Comando `evergarden:seed-demo`
      _(`app/Console/Commands/SeedDemoCommand.php`: 6 usuarios de demo (`@demo.evergarden.test`, contraseña `password`), cartas en los 6 estados directos + 1 aleatoria `held` (cola de moderación nunca vacía), 1 programación anual con ocurrencias materializadas, 3 posts de blog (incluida una `shared_letter` pendiente de consentimiento) + 1 comentario, todos los feature flags activados; idempotente (retira la tanda `@demo.evergarden.test` anterior en cascada) y se niega a correr en producción; reutiliza `BlogPublisher`/`OccurrenceGenerator`/`OccurrenceMaterializer` en vez de reimplementar. Doll verificada con solicitud: pendiente de Fase 3)_

### Front
- [x] Recursos de ayuda en Ajustes
      _(`SupportResourcesSection` en `SettingsView`; `api/support.ts` + `useSupportResources` (TanStack Query, país de `auth.user.country_code`, fallback internacional); estados carga/error/vacío; i18n es/en; `tel:` y enlace a web)_
- [x] Vista de programaciones + línea de tiempo de ocurrencias
      _(`SchedulesView` (lista + pausar/reanudar/eliminar) + `ScheduleForm` (crea, con selector de recurrencia/leap/tier/carta) + `ScheduleTimelineView` (ocurrencias materializadas + virtuales, asignar carta por fecha, enlace a seguimiento); `api/schedules.ts` + `useSchedules`; `api/features.ts` + `useFeature('schedules')` gatea el enlace de nav; i18n es/en; `api/__tests__/schedules.spec.ts`)_
- [x] Blog: feed, post, publicar, comentar, reaccionar
      _(`BlogFeedView` (filtros por tipo/etiqueta) + `PostView` (`PostBody` renderiza el doc tiptap sin la dependencia pesada, comentarios 1 nivel, barra de reacciones toggle) + `PostComposeView` (tipo, cuerpo, testimonio/carta origen para `shared_letter`, etiquetas, anónimo); `api/blog.ts` + `useBlog`; `useFeature('blog')` gatea el nav; i18n es/en; `api/__tests__/blog.spec.ts`)_
- [x] Flujo de solicitud y respuesta de consentimiento
      _(`ConsentRequestsView` (`/blog/consentimientos`): vista previa exacta anonimizada + dar/rechazar permiso vía `POST /consent-requests/{post}/respond`)_
- [x] Botella al mar (envío + cuota + respuesta anónima única)
      _(`BottleView` (elige borrador, muestra cuota + motivos de inelegibilidad, `Idempotency-Key` por instancia, aviso de `held`); `RandomLetterActions` en `MailboxReadView` (responder una vez + proponer correspondencia abierta); `api/random.ts` + `useRandom`; `useFeature('bottle_at_sea')` gatea el nav; i18n es/en; `api/__tests__/random.spec.ts`)_
- [x] Suscripción a push + gestión de permisos
      _(`PushSection` en `SettingsView`: toggle activar/desactivar → `usePush` (feature-detect, `Notification.requestPermission`, `pushManager.subscribe` con la VAPID key, `POST /push-subscriptions`); errores por código i18n; `api/push.ts` + `api/__tests__/push.spec.ts`)_
- [x] i18n completo es/en
      _(auditoría: `es.json`/`en.json` con paridad exacta de claves — 370/370, ninguna falta en ningún sentido; sin texto ni atributos (`placeholder`/`title`/`aria-label`/`alt`) sin pasar por `t()` en `src/**/*.vue`)_

---

## Fase 3 — Auto Memory Dolls

### Backend
- [x] `doll_profiles` + solicitud y verificación del rol
      _(3A: migración `doll_profiles` (capacidad, no rol); `DollProfile::markVerified()`/`markUnverified()` flipan `users.role` — nunca automático; `POST/PATCH /me/doll-profile` + `GET /me/doll-profile` (deviación) + `POST /me/doll-profile/availability`; `DollProfilePolicy` (perfil no verificado → 404 salvo al dueño); Filament `DollProfileResource` (cola de pendientes, verificar/retirar verificación, badge de nav). Sin pagos — ADR-0012)_
- [x] Directorio con filtros
      _(`GET /dolls?specialty=&language=&available=` solo perfiles verificados, `whereJsonContains` sobre jsonb, orden por `rating_avg`; `GET /dolls/{handle}` por `postal_handle` (deviación, consistente con `GET /users/{handle}`); nunca expone `email` ni `verified_at`/`max_concurrent_requests` a terceros)_
- [x] `doll_requests` con máquina de estados + `ExpireStaleDollRequestsJob`
      _(3B: `DollRequest` con métodos guardados `accept()`/`reject()`/`start()`/`cancel()`/`expire()` (409
      `INVALID_STATE_TRANSITION` si no aplica) — mismo patrón que `LetterDelivery`; `awaitClient()`/
      `resume()`/`complete()`/`rate()` ya existen en el modelo pero solo se disparan desde 3C (chat/
      drafts), no hay endpoint aún; `DollRequestPolicy` (cliente/Doll, 404 a terceros, como
      `LetterDeliveryPolicy`); `POST /doll-requests` resuelve `doll_handle`→uuid, exige verificada +
      disponible + cupo (`DollProfile::hasCapacity()`, cuenta solo `accepted`/`in_progress`/
      `awaiting_client`) y filtra PII en `target_recipient_hint` (`PiiScanner`); `GET /doll-requests
      ?role=&status=`; `ExpireStaleDollRequestsJob` cada hora)_
- [x] Reverb + `routes/channels.php` + doble validación en controlador
      _(3C: `laravel/reverb ^1.11` — obliga a bajar Guzzle 8→7, ADR-0013. `routes/channels.php` con
      `doll-request.{id}` (participante + `status->isOpenChannel()`) y `user.{id}`; `/broadcasting/auth`
      registrado vía `withBroadcasting(..., ['api','auth:sanctum'])` para que sirva a la PWA (cookie) y
      al móvil (Bearer), no sólo al guard `web`. Doble validación real: `DollChatController` y
      `DollDraftController` repiten la comprobación en cada llamada → `403 CHANNEL_CLOSED`. El evento
      `DollChatMessageSent` viaja con id/type/sender/fecha y **nada más**: el cuerpo se relee por API)_
- [x] `doll_chat_messages` + borradores versionados + aprobación
      _(3C: migración `doll_chat_messages` (`body` `encrypted`, `draft_payload` jsonb, `draft_version`
      con único `(doll_request_id, draft_version)`, `pii_flags`, índice `(doll_request_id, created_at)`);
      `GET/POST .../messages` (cursor **ascendente**, legible tras cerrar — sólo escribir exige canal
      abierto), `POST .../drafts` (sólo la Doll, versión asignada por el servidor),
      `POST .../drafts/{draft}/approve` (sólo el cliente, `scopeBindings()`). `DraftApprover` hace las
      tres cosas en una transacción: crea la `letter` con `author_id = client_id` + `doll_request_id`,
      sella el borrador y llama a `DollRequest::complete()`. `in_progress ⇄ awaiting_client` se alterna
      solo, según quién escriba. FK `letters.doll_request_id` por fin constreñida)_
- [x] Filtro anti-intercambio de contactos
      _(3C: `ContactExchangeGuard` sobre `PiiScanner` — **avisa, no bloquea** (al revés que en aleatorias):
      el mensaje sale, vuelve con `pii_flags`, se inserta un mensaje `system` que ambas partes ven y se
      registra `moderation_actions` `warn`/`filter` `contact_exchange_in_doll_chat`. Además
      `reportable_type: doll_chat_message` cableado, exigiendo ser parte de la solicitud → 404 si no)_
- [x] Valoraciones + `RecalculateDollRatingsJob`
      _(3D: `POST /doll-requests/{id}/rate` (sólo el cliente, una vez, sólo sobre `completed`;
      `409 ALREADY_RATED`); el endpoint **no** toca el agregado. `RecalculateDollRatingsJob` (horario,
      cola `maintenance`) recalcula `rating_avg`/`rating_count`/`completed_requests_count` **desde
      cero** con un solo UPDATE correlacionado — una media por incrementos se desvía en cuanto algo se
      borra. `config/dolls.php`: `min_ratings_to_display` (3) hace que `rating_avg` salga `null` hasta
      que la media significa algo)_
- [x] `PurgeOldDollChatsJob` (90 días)
      _(3D: diario 04:00, cola `maintenance`. Borra **sólo la transcripción** de solicitudes en estado
      terminal, fechadas por `COALESCE(completed_at, cancelled_at, rejected_at, updated_at)`; la
      solicitud (auditoría + valoración) y la carta del cliente sobreviven. Una conversación abierta no
      se toca nunca. Ventana en `config/dolls.php`)_
- [x] (Opcional) Stripe Connect + pagos retenidos — **diferido a propósito, ver ADR-0012**

### Front
- [x] Directorio de Dolls + perfil
      _(`DollDirectoryView` (filtros especialidad/idioma/disponibilidad) + `DollProfileView` (bio, especialidades, valoración, CTA "pedir ayuda") + `BecomeDollView` (solicitar el rol / editar / toggle de disponibilidad, estado pendiente vs verificado); `api/dolls.ts` + `useDolls`; `useFeature('dolls')` gatea el nav; i18n es/en; `api/__tests__/dolls.spec.ts`)_
- [x] Crear solicitud con brief
      _(`DollRequestNewView` (brief: ocasión, notas, pista sobre destinatario con aviso anti-PII, tono,
      fecha límite) enlazada desde el CTA de `DollProfileView`; `DollRequestsListView` ("mis
      solicitudes", filtro cliente/Doll) + `DollRequestDetailView` (detalle + aceptar/rechazar/empezar/
      cancelar según el rol del usuario respecto a la solicitud); `api/dollRequests.ts` + `useDollRequests`;
      i18n es/en; `api/__tests__/dollRequests.spec.ts`)_
- [x] Chat en tiempo real (Echo)
      _(3C: `src/lib/echo.ts` (Reverb, conexión **perezosa** — sólo al abrir un chat; `disconnectEcho()`
      al cerrar sesión), `DollRequestChatView` (`/dolls/solicitudes/:id/chat`) con transcripción viva o
      de sólo lectura según el estado, `ChatMessage` (texto / aviso del sistema / tarjeta de borrador),
      `api/dollChat.ts` + `useDollChat`; si `VITE_REVERB_KEY` está vacío cae a refetch cada 15 s en vez
      de romperse; i18n es/en; `api/__tests__/dollChat.spec.ts`. Indicador de escritura (3D) por
      **whisper** de Echo: cliente a cliente, nunca se guarda ni pasa por el servidor, throttle de 1/s y
      caduca solo a los 3 s — es azúcar de presencia, no contenido de la conversación)_
- [x] Visor de borradores versionados + aprobación
      _(3C: la tarjeta de borrador vive en la propia transcripción y renderiza el doc tiptap con
      `PostBody` (sin arrastrar prosemirror); `DraftComposer` para que la Doll comparta versiones con
      `LetterEditor`; aprobar redirige al editor de la carta recién creada, que ya es del cliente)_
- [x] Panel de la Doll (bandeja de solicitudes)
      _(3D: `DollPanelView` (`/dolls/panel`, sólo rol `doll` verificado, enlace de nav condicionado):
      disponibilidad con toggle, ocupación `activos/max` (cuenta lo mismo que el backend: `pending` no
      ocupa cupo), bandeja de pendientes con aceptar/rechazar en línea y caducidad visible, encargos en
      curso con acceso directo al chat, y terminados con su valoración o «sin valorar»)_
- [x] Valoración post-servicio
      _(3D: `RatingForm` en `DollRequestDetailView`, sólo para el cliente y sólo sobre `completed`;
      estrellas accesibles (`aria-pressed`, `aria-label`) + comentario opcional; una vez valorada se
      muestra en modo lectura, sin forma de cambiarla. No es modal ni insiste: «sin valorar» es un final
      perfectamente válido. El directorio y el perfil muestran «sin valoraciones suficientes» mientras
      `rating_avg` sea `null`)_

---

## Fase 4 — Móvil y refinamiento

- [x] Scramble → `openapi.json` → tipos TS generados
      _(4A: `dedoc/scramble`, `config/scramble.php` (`api_path: api/v1`, sólo v1 — `routes/api.php` es un
      manifiesto de versiones, no contrato) y `ApiDocsServiceProvider` (server **relativo** `/api/v1`,
      porque el valor de config pasa por `url()` y hornearía el `APP_URL` de quien genere el fichero;
      + gate `viewApiDocs` = staff activo, que cierra el ítem "/docs protegido" del checklist).
      `docs/api/openapi.json` versionado: 82 rutas, 66 esquemas. **Desviación: los tipos generados NO
      sustituyen a `src/types/api.ts`** — son más débiles (`status: string` en vez de la unión literal),
      y de esas uniones dependen los `switch` exhaustivos y las claves de i18n. En su lugar:
      `src/types/openapi.d.ts` (generado, `npm run api:types`) + `src/types/contract.ts`, que falla el
      `typecheck` nombrando la clave si una interfaz a mano se desvía del esquema. ADR-0014.
      Hallazgos reales al activarlo: `DeliveryResource` exponía `mode` y `correspondence_opened` sin
      declarar en el front desde Fase 2; `avatar_url` se publicaba como `null` en vez de `string|null`
      (arreglado extrayendo `PartyResource`, que además quita la duplicación en 4 sitios del módulo de
      Dolls); `GET /features` publicaba `features: string` (arreglado con `#[Response]`).
      `ApiDocsTest` falla si una ruta `api/v1` no está en el documento versionado)_
- [ ] Capacitor sobre la PWA + push nativo (FCM/APNs)
- [x] Sincronización delta (`updated_since`)
      _(4B: `App\Support\DeltaSync` reutilizable, aplicado a `GET /mailbox`, `/deliveries`, `/letters` y
      `/notifications`. Lo que lo hace correcto y no un simple filtro: **la marca de agua la emite el
      servidor** (`meta.synced_at`, tomada *antes* de la consulta — el reloj del cliente adelantado se
      saltaría filas para siempre, y solapar es inofensivo mientras un hueco no lo es); ventana
      **estrictamente `>`** (con `>=` la fila del borde vuelve en cada sincronización); durante un delta
      el **orden pasa a `updated_at` asc** (paginar por `delivered_at` filtrando por `updated_at` da
      páginas que no componen); y **`meta.deleted_ids`** como lápidas para `letters` (borrado lógico) —
      sin ellas un borrador borrado se queda en el dispositivo para siempre. Fecha ilegible →
      `422 INVALID_UPDATED_SINCE`, nunca un silencio. Documentado en `_convenciones.md` y declarado en
      OpenAPI con `#[QueryParameter]`, porque al mover la lectura a `DeltaSync` Scramble dejó de
      inferirlo. El consumidor previsto es el cliente móvil (Capacitor, pendiente); la PWA sigue con
      TanStack Query + caché del service worker. `DeltaSyncTest`: 17 casos)_
- [x] Exportación de cartas a PDF
      _(4C: `dompdf/dompdf` (PHP puro) envuelto en `LetterPdfRenderer`, **no** `spatie/laravel-pdf` como
      sugería la spec: ese lanza un Chromium headless vía Node, y la contenerización sigue diferida, así
      que ese coste recaería sobre cada máquina y CI. ADR-0015. `GET /letters/{id}/pdf` (la autora) y
      `GET /mailbox/{id}/pdf` (quien la recibió); cada puerta reutiliza el permiso que ya existía, así
      que **nunca** exporta una entrega `in_transit` ni revela un remitente que el buzón sigue ocultando
      (`SenderView`). `Cache-Control: private, no-store` + `throttle:export-pdf` 10/min.
      `TiptapContent::toHtml()` escapa al salir — el cuerpo se saneó al entrar, pero uno guardado antes
      de un cambio de lista blanca sería un XSS almacenado hacia el renderizador. Limitación anotada:
      las tipografías del catálogo no viajan como ficheros, así que el PDF cae a la serif de Dompdf;
      papel, tinta y marco sí se conservan. `LetterPdfTest`: 13 casos)_
- [x] Búsqueda — **en Postgres, sin Meilisearch** (ADR-0016)
      _(4D: `GET /posts?q=`. La columna `search_vector`, su índice GIN y su trigger existían desde 2D y
      **nadie los usaba**; esto los enciende en vez de desplegar un servicio nuevo. Dos arreglos que se
      notaban al primer intento: `simple` → `spanish` (sin lematizar, "cartas" no encontraba "carta") y
      título con peso A sobre cuerpo B, que obligó a una función de trigger propia porque
      `tsvector_update_trigger()` no asigna pesos (migración 000550, reindexa en sitio).
      `websearch_to_tsquery`, no `to_tsquery`: acepta comillas, `-palabra` y hasta un `&` suelto sin
      convertir una errata en un 500. **El ranking obligó a una subconsulta** — el cursor compara las
      columnas del ORDER BY como columnas reales, así que un `search_rank` calculado reventaba en la
      página 2; una tabla derivada lo convierte en columna de verdad (hay test). Front: buscador con
      debounce de 350 ms en `BlogFeedView`. `PostSearchTest`: 20 casos. **No busca cartas privadas**:
      `letters.body` va cifrado y eso sería otra función, con su propio consentimiento)_
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
