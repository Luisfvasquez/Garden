# Progreso del proyecto

**Actualizar al cerrar cada tarea.** Este archivo es lo que le dice al agente qué existe ya.
Formato: `[ ]` pendiente · `[~]` en curso · `[x]` terminado.

Última actualización: 2026-08-28 — arranque de cimientos backend (Fase 0).

---

## Fase 0 — Cimientos

### Backend
- [ ] Repo, docker-compose (php, nginx, pgsql, redis, horizon, scheduler, reverb, mailpit, minio)
      _(diferido: el entorno Laragon local —pgsql 17, redis— ya funciona; la contenerización es tarea aparte)_
- [x] Laravel 11 skeleton API + `install:api` _(Laravel 13 en el entorno; `routes/api.php` → manifiesto de versiones → `routes/api/v1.php`)_
- [~] Sanctum configurado (cookie SPA + tokens móviles) y CORS
      _(hecho: `statefulApi`, `config/cors.php` con orígenes explícitos + credenciales, expiración de token móvil 30d, modelo `PersonalAccessToken` con UUID. Falta: endpoints `auth/login` y `auth/token`)_
- [~] Estructura `/api/v1` con `_convenciones.md` aplicada (Resources, FormRequests, error handler)
      _(hecho: envelope de error único con `error_code` + `meta.request_id`, `X-Request-Id`, versionado, patrón API Resource. FormRequests se añaden por endpoint en la fase de auth)_
- [x] Pint + Larastan 6 + Pest en CI
      _(en verde local + `.github/workflows/backend-ci.yml`: Pint `--test`, PHPStan nivel 6, Pest sobre servicio Postgres 17)_
- [x] Migración `users` + `user_settings` + generación de `postal_handle`
      _(+ `feature_flags`, `personal_access_tokens` UUID; `postal_handle` `nombre-XXXX` hex en `User::creating`)_
- [ ] Auth: registro (con verificación de edad), login, logout, verificación email, reset
- [ ] `GET /me`, `PATCH /me`, ajustes, avatar
- [ ] `GET /users/{handle}` (perfil público mínimo)
- [ ] Policies base + tests de aislamiento entre usuarios
- [x] `GET /health`, `GET /features` (feature flags)

### Front
- [ ] Vite + Vue 3 + TS + Tailwind + Pinia + Router con guards
- [ ] `api/client.ts` con interceptores (auth, 401, 419, 429, error_code)
- [ ] Sistema de diseño base: tokens, tipografía, paleta, componentes primitivos
- [ ] Vistas de auth: registro, login, verificar email, recuperar contraseña
- [ ] Layout autenticado + navegación
- [ ] i18n es/en con las claves de auth

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
