# Proyecto "Evergarden" — Especificación técnica completa

> Plataforma de correspondencia escrita inspirada en *Violet Evergarden*.
> Backend Laravel desacoplado (API-first) + Frontend Vue 3 PWA + futura app móvil sobre la misma API.

**Versión del documento:** 1.0
**Estado:** especificación base, lista para extenderse con nuevos módulos.

---

## 0. Índice

1. [Validación de la propuesta inicial](#1-validación-de-la-propuesta-inicial)
2. [Principios de arquitectura](#2-principios-de-arquitectura)
3. [Stack tecnológico](#3-stack-tecnológico)
4. [Repositorios, entornos y despliegue](#4-repositorios-entornos-y-despliegue)
5. [Autenticación y autorización](#5-autenticación-y-autorización)
6. [Modelo de datos completo](#6-modelo-de-datos-completo)
7. [Máquinas de estado](#7-máquinas-de-estado)
8. [Módulos funcionales](#8-módulos-funcionales)
9. [Colas, jobs y tareas programadas](#9-colas-jobs-y-tareas-programadas)
10. [Tiempo real (WebSockets)](#10-tiempo-real-websockets)
11. [Diseño de la API](#11-diseño-de-la-api)
12. [Frontend PWA](#12-frontend-pwa)
13. [Seguridad y privacidad](#13-seguridad-y-privacidad)
14. [Observabilidad, testing y CI/CD](#14-observabilidad-testing-y-cicd)
15. [Roadmap por fases](#15-roadmap-por-fases)
16. [Puntos de extensión futuros](#16-puntos-de-extensión-futuros)
17. [Anexos](#17-anexos)

---

## 1. Validación de la propuesta inicial

### 1.1 Lo que está bien planteado

| Punto | Comentario |
| --- | --- |
| Laravel API + Vue separados | Correcto para reutilizar el backend en móvil. Es la decisión más importante y está bien tomada. |
| Sanctum | Suficiente para PWA (cookies) y móvil (tokens personales). No necesitas Passport/OAuth2 salvo que abras la API a terceros. |
| Redis + Horizon | Imprescindible con el volumen de jobs programados que implica este producto. |
| Separar `letters` de `letter_deliveries` | Acierto clave: permite una carta con N destinatarios/N fechas sin duplicar contenido. |
| Reverb solo para el canal Doll | Coherente con la regla de negocio "todo lo demás es por cartas". |
| Metadatos estéticos en JSON | Buena decisión, evita migraciones cada vez que agregues un tipo de papel o sello. |

### 1.2 Inconsistencias detectadas en la propuesta original

Estos son errores reales que romperían la implementación tal cual está escrita:

1. **`letter_deliveries` no tiene columna `status`**, pero el comando del cron hace
   `LetterDelivery::where('status', 'in_transit')`. Falta definirla. En esta especificación el estado
   vive en `letter_deliveries` (por entrega), no en `letters`.

2. **Duplicación de `recipient_id`** en `letters` y en `letter_deliveries`. Genera dos fuentes de
   verdad. Solución: `letters` NO guarda destinatario; solo contenido y estética. El destinatario
   siempre vive en la entrega.

3. **La tabla `Mailbox` aparece en el diagrama pero no está definida.** No hace falta como tabla
   física: el buzón es una *vista* sobre `letter_deliveries` filtrando por `recipient_id` y
   `status = delivered`. Lo que sí falta es `read_at` / `opened_at` en la entrega.

4. **El `chunkById` del cron tiene un bug de recorrido.** Filtras por `status = 'in_transit'` y dentro
   del bucle cambias ese mismo campo. Al pasar a la siguiente página, el conjunto ya cambió y se
   saltan registros. Solución: `chunkById` sobre IDs con cursor estable, o marcar primero un lote
   con un `dispatch_batch_id` y luego procesarlo. Detalle en §9.

5. **`inRandomOrder()` para la botella al mar** hace un full scan y no escala. Además no contempla
   bloqueos, reportes previos ni límite de cartas recibidas por día. Ver §8.6.

6. **`scheduled_for = date_add(now(), interval i year)` para recurrencias** ignora zonas horarias,
   años bisiestos (29 de febrero) y el hecho de que el usuario puede cambiar de país. Ver §8.4.

7. **Fórmula de tránsito incompleta.** `delivered_at = dispatch_time + transit_duration` no distingue
   entre *fecha programada de envío* y *momento real de despacho*. Se necesitan tres marcas de
   tiempo: `scheduled_for`, `dispatched_at`, `delivered_at`.

### 1.3 Lo que falta y es crítico

Sin esto el producto no es lanzable:

- **Moderación y anti-abuso.** Una función que envía texto libre a un desconocido aleatorio es un
  vector directo de acoso, spam y contenido ilegal. Necesitas reportes, bloqueos, límites de tasa,
  filtrado y cola de revisión. Es el mayor riesgo del proyecto. Ver §8.10.
- **Bloqueo de usuarios y lista negra.** Debe afectar a cartas dirigidas, aleatorias, comentarios y
  solicitudes a Dolls.
- **Política de retención y cuentas inactivas.** Una carta programada a 10 años implica: ¿qué pasa si
  el remitente borra su cuenta? ¿si el destinatario la borra? ¿si el correo rebota? Ver §13.4.
- **Zonas horarias.** Todo en UTC en base de datos, `timezone` por usuario, render en local.
- **Inmutabilidad de la carta enviada.** Una vez despachada no se edita ni se borra el contenido para
  el destinatario. Solo se permite cancelar mientras esté `queued`.
- **Pagos**, si el servicio Doll es remunerado (`hourly_or_letter_rate` lo sugiere). Aunque sea de
  fase 2, el modelo de datos debe dejarle sitio.
- **Adjuntos y almacenamiento** (fotos, sellos personalizados, audio). Define límites desde el día 1.
- **Versionado de API** (`/api/v1`) desde el primer commit, o la app móvil te obligará a romper la web.
- **Verificación de email** y recuperación de contraseña, obvio pero ausente en la propuesta.
- **Consentimiento explícito** para recibir cartas anónimas (opt-in, nunca opt-out por defecto).

### 1.4 Recomendaciones de producto

- **Cancelación con ventana de arrepentimiento.** Mientras la entrega esté en `queued` o dentro de los
  primeros N minutos de `in_transit`, permite retirar la carta. Es emocionalmente importante en un
  producto de este tipo.
- **Límite de tránsito visible.** Muestra "llegará aproximadamente en 4 horas" en vez de un contador
  exacto al segundo: refuerza la sensación postal y te da margen operativo.
- **La Doll no ve el buzón del cliente.** Solo el brief y el chat de esa solicitud. Aísla el rol.
- **Cartas "en tránsito" no deben ser visibles ni notificadas al destinatario.** La sorpresa es el
  producto.
- **Seudónimo obligatorio en el blog anónimo**, con vínculo interno al autor real para moderación.

---

## 2. Principios de arquitectura

1. **API-first.** El backend no renderiza vistas. Todo cliente (PWA, iOS, Android, futuros) consume el
   mismo contrato HTTP/JSON.
2. **Stateless en la capa HTTP.** Nada de estado en memoria del proceso; el estado vive en base de
   datos y Redis. Permite escalar horizontalmente.
3. **Asíncrono por defecto.** Todo lo que no sea respuesta inmediata al usuario va a cola: envíos,
   notificaciones, moderación, emails, generación de PDFs.
4. **Eventos de dominio.** Cada transición relevante emite un evento (`LetterDispatched`,
   `LetterArrived`, `DollRequestAccepted`). Los listeners se pueden añadir sin tocar el core: es tu
   principal punto de extensión futura.
5. **Autorización centralizada en Policies.** Nunca `if ($user->role === 'admin')` disperso por
   controladores.
6. **Idempotencia.** Todo job debe poder ejecutarse dos veces sin duplicar efectos (usa
   `unique` jobs y guardas de estado).
7. **Contrato explícito.** API Resources de Laravel para toda respuesta; nunca `return $model`.
8. **Todo en UTC** en persistencia. Conversión únicamente en la frontera de presentación.

---

## 3. Stack tecnológico

### 3.1 Backend

| Componente | Elección | Nota |
| --- | --- | --- |
| Framework | Laravel 11+ (skeleton API) | `php artisan install:api` |
| PHP | 8.3+ | Enums nativos, readonly, tipado estricto |
| Base de datos | PostgreSQL 16 | `jsonb`, índices GIN, mejor con full-text search y particionado |
| Cache/Colas | Redis 7 | Colas, cache, locks, rate limiting |
| Supervisor de colas | Laravel Horizon | Métricas, reintentos, balanceo |
| WebSockets | Laravel Reverb | Self-hosted, protocolo Pusher |
| Auth | Laravel Sanctum | Cookies para PWA, tokens para móvil |
| Almacenamiento | S3 / R2 / MinIO | Adjuntos e imágenes, nunca disco local |
| Búsqueda | Laravel Scout + Meilisearch | Fase 2, para el blog |
| Emails | Resend / Postmark / SES | Con webhooks de rebote |
| Push | web-push (VAPID) + FCM/APNs | Web y móvil |
| Tests | Pest | Feature tests sobre la API |
| Estilo | Laravel Pint + PHPStan (larastan) nivel 6 | En CI |

> **PostgreSQL sobre MySQL:** el proyecto usa mucho JSON, búsquedas de texto en el blog, y
> potencialmente particionado de `letter_deliveries` por fecha. Postgres gana en los tres.

### 3.2 Frontend

| Componente | Elección |
| --- | --- |
| Framework | Vue 3 (Composition API, `<script setup>`) |
| Build | Vite 5 |
| Estado | Pinia (+ `pinia-plugin-persistedstate`) |
| Rutas | Vue Router 4 con guards por rol |
| HTTP | Axios con interceptores (auth, refresh, errores) |
| PWA | `vite-plugin-pwa` (Workbox) |
| Estilos | Tailwind CSS + tokens de diseño propios |
| Formularios | VeeValidate + Zod/Yup |
| Editor de carta | Tiptap (ProseMirror) con extensiones limitadas |
| i18n | vue-i18n (es/en desde el inicio) |
| Tiempo real | laravel-echo + pusher-js apuntando a Reverb |
| Tests | Vitest + Vue Test Utils; Playwright para E2E |

### 3.3 Dirección visual

- Tipografía serif para el cuerpo de la carta (Cormorant Garamond, EB Garamond, Lora).
- Sans humanista para la UI de la aplicación (Inter, Source Sans).
- Paleta cálida: crema/pergamino, sepia, verde oliva apagado, azul violeta (guiño a Violet), lacre rojo.
- Texturas sutiles de papel, no imágenes pesadas: usa `background-blend-mode` y SVG.
- Animaciones lentas y suaves. Nada rebota. Los transitions de apertura de carta son parte del producto.
- Modo oscuro: "escritorio de noche a la luz de una lámpara", no gris azulado genérico.
- **Accesibilidad:** contraste AA mínimo, respeto a `prefers-reduced-motion`, tamaño de fuente escalable
  (la carta debe ser legible para todas las edades).

---

## 4. Repositorios, entornos y despliegue

### 4.1 Estructura

```
evergarden-api/          # Laravel
evergarden-web/          # Vue PWA
evergarden-mobile/       # Fase 3 (Capacitor / Flutter / RN)
evergarden-infra/        # docker-compose, IaC, CI reutilizable
```

Alternativa: monorepo con pnpm workspaces + carpeta `api/`. Recomendado solo si vas a trabajar solo;
repos separados si va a haber equipo.

### 4.2 Entornos

| Entorno | Dominio API | Dominio Web |
| --- | --- | --- |
| Local | `api.evergarden.test` | `evergarden.test` |
| Staging | `api.staging.evergarden.app` | `staging.evergarden.app` |
| Producción | `api.evergarden.app` | `evergarden.app` |

**Importante:** API y Web deben compartir dominio raíz (`*.evergarden.app`) para que las cookies de
Sanctum funcionen en la PWA sin caer en problemas de cookies de terceros.

### 4.3 Servicios en ejecución (producción)

```
nginx + php-fpm        → API HTTP
horizon                → workers de cola (supervisord)
scheduler              → php artisan schedule:work (o cron cada minuto)
reverb                 → php artisan reverb:start (detrás de nginx con WS upgrade)
postgres
redis
```

### 4.4 docker-compose local (referencia)

Servicios: `app` (php-fpm), `nginx`, `pgsql`, `redis`, `horizon`, `scheduler`, `reverb`, `mailpit`,
`minio`. Un solo `docker compose up` debe dejar el entorno completo funcionando.

---

## 5. Autenticación y autorización

### 5.1 Estrategia dual de Sanctum

| Cliente | Modo | Detalle |
| --- | --- | --- |
| PWA (mismo dominio raíz) | Cookie SPA | `/sanctum/csrf-cookie` → login → cookie `HttpOnly` `SameSite=Lax` |
| App móvil | Token personal | `POST /api/v1/auth/token` devuelve token con abilities y `device_name` |

Configuración obligatoria:

- `SANCTUM_STATEFUL_DOMAINS=evergarden.app,staging.evergarden.app,evergarden.test`
- `SESSION_DOMAIN=.evergarden.app`
- CORS: `supports_credentials: true`, orígenes explícitos (nunca `*`).

**Expiración de tokens móviles:** define `expiration` en `config/sanctum.php` (ej. 30 días) y un
endpoint de renovación. Guarda `last_used_at` para permitir al usuario cerrar sesión en dispositivos
concretos desde su perfil.

### 5.2 Roles

Enum `UserRole`: `client`, `doll`, `moderator`, `admin`.

- Un usuario nace como `client`.
- `doll` es un rol **aditivo**: una Doll también puede escribir sus propias cartas. Modélalo como
  `role` principal + tabla `doll_profiles` que determina la capacidad. Si prevés más combinaciones,
  usa `spatie/laravel-permission` desde el inicio.
- `moderator` accede a la cola de reportes, no al panel de administración completo.

### 5.3 Policies necesarias

| Policy | Reglas clave |
| --- | --- |
| `LetterPolicy` | `view`: autor o destinatario con entrega `delivered`. `update`/`delete`: solo autor y solo en `draft`. |
| `LetterDeliveryPolicy` | `view`: destinatario si `delivered`, remitente siempre. `cancel`: remitente si `queued`. |
| `DollRequestPolicy` | `view`: cliente o doll asignada. `accept`/`reject`: solo la doll destinataria. |
| `DollChatMessagePolicy` | `create`: solo si la solicitud está `in_progress` y el usuario es parte. |
| `PublicPostPolicy` | `update`/`delete`: autor o moderador. |
| `CommentPolicy` | igual que arriba. |
| `ReportPolicy` | `viewAny`: moderador/admin. |

### 5.4 Endurecimiento

- Verificación de email obligatoria antes de enviar cartas (previene spam).
- Rate limiting por acción, no global (ver §11.4).
- 2FA opcional (`laravel/fortify` o TOTP manual) para Dolls y admins.
- Registro de sesiones activas y dispositivos.
- Contraseñas: `Password::defaults()` con `uncompromised()` en producción.

---

## 6. Modelo de datos completo

### 6.1 Diagrama de relaciones

```
                          ┌──────────────┐
                          │    users     │
                          └──────┬───────┘
        ┌────────────────────────┼────────────────────────────┐
        │                        │                            │
        │                 ┌──────┴───────┐            ┌───────┴────────┐
        │                 │ user_settings │           │  doll_profiles │
        │                 └──────────────┘            └───────┬────────┘
        │                                                     │
        │                                             ┌───────┴────────┐
        │                                             │ doll_requests  │
        │                                             └───────┬────────┘
        │                                                     │
        │                                        ┌────────────┴─────────────┐
        │                                        │   doll_chat_messages     │
        │                                        └──────────────────────────┘
        │
   ┌────┴──────┐
   │  letters  │────────< letter_attachments
   └────┬──────┘
        │
        ├──────< letter_deliveries ────< delivery_events
        │              │
        │              └──── (buzón del destinatario)
        │
        └──────< letter_schedules  (plantilla de recurrencia)

   ┌──────────────┐        ┌──────────┐        ┌────────────┐
   │ public_posts │───────<│ comments │        │  reactions │
   └──────────────┘        └──────────┘        └────────────┘

   ┌─────────┐   ┌─────────┐   ┌───────────────┐   ┌───────────────┐
   │ blocks  │   │ reports │   │ notifications │   │ push_subs     │
   └─────────┘   └─────────┘   └───────────────┘   └───────────────┘
```

### 6.2 Tablas

#### `users`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | UUID, no autoincremental: evita enumeración y facilita sincronización móvil |
| `name` | string | |
| `pen_name` | string, nullable, unique | Seudónimo público para el blog |
| `postal_handle` | string, unique | Identificador postal `nombre-XXXX` (4 hex). Generado en el registro (§8.1) |
| `postal_handle_rotated_at` | timestamp, nullable | Última rotación. Límite: 1 cada 30 días (`POST /me/postal-handle/rotate`) |
| `email` | string, unique | |
| `email_verified_at` | timestamp, nullable | |
| `password` | string | |
| `role` | enum | `client`, `doll`, `moderator`, `admin` |
| `status` | enum | `active`, `suspended`, `deactivated`, `deleted` |
| `avatar_path` | string, nullable | |
| `bio` | text, nullable | |
| `country_code` | char(2), nullable | ISO 3166-1 alpha-2 |
| `timezone` | string | IANA, default `UTC` |
| `locale` | string | `es`, `en` |
| `accepts_random_letters` | boolean | **default `false`** (opt-in explícito) |
| `random_letters_daily_cap` | tinyint | Default 3 |
| `last_active_at` | timestamp | |
| `deactivated_at` | timestamp, nullable | |
| `deletes_at` | timestamp, nullable | Momento en que se consuma el borrado con gracia de 30 días (`DELETE /me`) |
| `created_at` / `updated_at` / `deleted_at` | | SoftDeletes |

Índices: `email`, `(status, accepts_random_letters, last_active_at)`, `pen_name`, `postal_handle`.

#### `user_settings`

Separado de `users` para no ensuciar la tabla principal y permitir crecer.

| Campo | Tipo | Notas |
| --- | --- | --- |
| `user_id` | uuid (PK/FK) | |
| `notify_email` | boolean | |
| `notify_push` | boolean | |
| `notify_on_arrival` | boolean | Carta llegó al buzón |
| `notify_on_dispatch_confirm` | boolean | Confirmación de despacho propio |
| `notify_on_doll_message` | boolean | |
| `notify_on_blog_comment` | boolean | |
| `share_read_receipts` | boolean | default `false`. Permite que el remitente vea `read_at` de sus cartas |
| `quiet_hours_start` / `quiet_hours_end` | time, nullable | No notificar de madrugada (hora local) |
| `theme` | enum | `light`, `dark`, `system` |
| `preferred_paper_style` | string, nullable | |
| `show_transit_countdown` | boolean | Contador exacto vs estimación difusa |

#### `doll_profiles`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `user_id` | uuid (FK, unique) | |
| `headline` | string | "Especialista en cartas de despedida" |
| `bio` | text | |
| `specialties` | jsonb | `["amor","duelo","disculpa","celebración","negocios"]` |
| `languages` | jsonb | `["es","en","ja"]` |
| `tone_tags` | jsonb | `["formal","íntimo","poético"]` |
| `rate_type` | enum | `free`, `per_letter`, `hourly` |
| `rate_amount` | integer, nullable | En centavos, moneda menor |
| `currency` | char(3), nullable | |
| `is_available` | boolean | |
| `max_concurrent_requests` | tinyint | Default 3 |
| `rating_avg` | decimal(3,2) | Desnormalizado |
| `rating_count` | integer | |
| `completed_requests_count` | integer | |
| `response_time_avg_minutes` | integer, nullable | |
| `portfolio` | jsonb, nullable | Muestras públicas (con consentimiento) |
| `verified_at` | timestamp, nullable | Verificación manual por admin |

#### `letters`

**Solo contenido y estética. Sin destinatario.**

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `author_id` | uuid (FK users) | |
| `title` | string, nullable | |
| `body` | text (cifrado en reposo) | HTML restringido o JSON de Tiptap |
| `body_plain` | text, nullable | Versión plana para búsqueda/moderación |
| `word_count` | integer | |
| `style` | jsonb | `{paper:"parchment", font:"garamond", ink:"sepia", seal:"wax_red", stamp:"lavender", border:"art_nouveau"}` |
| `kind` | enum | `direct`, `random`, `unaddressed`, `doll_draft` |
| `is_locked` | boolean | `true` en cuanto tiene una entrega despachada |
| `doll_request_id` | uuid, nullable (FK) | Si nació de una sesión con Doll |
| `moderation_status` | enum | `pending`, `approved`, `flagged`, `rejected` |
| `created_at` / `updated_at` / `deleted_at` | | |

Índices: `author_id`, `(kind, moderation_status)`.

#### `letter_attachments`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `letter_id` | uuid (FK) | |
| `type` | enum | `image`, `audio`, `pressed_flower` (decorativo) |
| `disk` / `path` | string | |
| `original_name` | string | |
| `mime_type` / `size_bytes` | | |
| `metadata` | jsonb | dimensiones, duración |

Límites sugeridos: 3 adjuntos por carta, 5 MB por imagen, 60 s de audio.

#### `letter_deliveries`

**El corazón del sistema.** Una fila = una carta entregada a una persona en una fecha.

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `letter_id` | uuid (FK) | |
| `sender_id` | uuid (FK users) | Denormalizado para consultas rápidas |
| `recipient_id` | uuid (FK users), nullable | `null` hasta asignar en modo aleatorio |
| `recipient_email` | string, nullable | Invitación a alguien fuera del sistema (fase 2) |
| `schedule_id` | uuid (FK letter_schedules), nullable | Si proviene de una recurrencia |
| `status` | enum | `queued`, `in_transit`, `delivered`, `read`, `cancelled`, `failed`, `blocked` |
| `delivery_mode` | enum | `direct`, `random` |
| `scheduled_for` | timestamp (UTC) | Cuándo debe **salir** de la oficina postal |
| `dispatched_at` | timestamp, nullable | Cuándo salió realmente |
| `transit_duration_minutes` | integer | Calculado al despachar |
| `estimated_delivery_at` | timestamp, nullable | Lo que se muestra al remitente |
| `delivered_at` | timestamp, nullable | Llegada real al buzón |
| `read_at` | timestamp, nullable | Primera apertura |
| `cancelled_at` | timestamp, nullable | |
| `failure_reason` | string, nullable | |
| `attempts` | tinyint | |
| `is_anonymous` | boolean | El destinatario no ve quién escribe |
| `reveal_sender_at` | timestamp, nullable | Anonimato temporal (revelar después) |
| `dispatch_batch_id` | uuid, nullable | Para procesamiento idempotente por lotes |
| `created_at` / `updated_at` | | |

Índices críticos:

```sql
CREATE INDEX idx_deliveries_dispatch ON letter_deliveries (status, scheduled_for)
    WHERE status = 'queued';
CREATE INDEX idx_deliveries_arrival  ON letter_deliveries (status, delivered_at)
    WHERE status = 'in_transit';
CREATE INDEX idx_deliveries_mailbox  ON letter_deliveries (recipient_id, status, delivered_at DESC);
CREATE INDEX idx_deliveries_sender   ON letter_deliveries (sender_id, created_at DESC);
```

> Los índices parciales (`WHERE`) son una razón fuerte para elegir PostgreSQL: la tabla crecerá
> indefinidamente pero los índices de trabajo se mantienen pequeños.

**Particionado (a considerar en fase 3):** particionar por `RANGE (scheduled_for)` anual. Con cartas
programadas a 10 años vista es la única forma de mantener el rendimiento.

#### `delivery_events`

Auditoría inmutable de cada transición. Alimenta el "seguimiento postal" que puede ver el remitente.

| Campo | Tipo |
| --- | --- |
| `id` | uuid (PK) |
| `letter_delivery_id` | uuid (FK) |
| `event` | enum: `created`, `queued`, `dispatched`, `in_transit`, `sorting_office`, `out_for_delivery`, `delivered`, `read`, `cancelled`, `failed` |
| `occurred_at` | timestamp |
| `metadata` | jsonb (nodo postal ficticio, retraso aplicado, etc.) |

> Detalle de producto: inventa "oficinas de clasificación" ficticias con nombres del universo del
> anime para el tracking. Cuesta poco y aporta muchísimo a la experiencia.

#### `letter_schedules`

Plantilla de envíos recurrentes (ej. un cumpleaños durante 10 años).

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `letter_id` | uuid (FK), nullable | Si todas las ocurrencias comparten carta |
| `owner_id` | uuid (FK users) | |
| `recipient_id` | uuid (FK users), nullable | |
| `name` | string | "Cumpleaños de mamá" |
| `recurrence_type` | enum | `once`, `yearly`, `monthly`, `weekly`, `custom_dates` |
| `anchor_date` | date | Fecha base en la zona del propietario |
| `local_time` | time | Hora local deseada de llegada |
| `timezone` | string | Congelada al crear el schedule |
| `occurrences_total` | integer, nullable | Ej. 10 |
| `occurrences_generated` | integer | |
| `custom_dates` | jsonb, nullable | Lista explícita de fechas |
| `letters_map` | jsonb, nullable | `{ "2027-04-12": "<letter_uuid>", ... }` — una carta distinta por ocurrencia |
| `status` | enum | `active`, `paused`, `completed`, `cancelled` |
| `next_generation_at` | timestamp | Para generación perezosa |

#### `public_posts`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `author_id` | uuid (FK users) | Siempre poblado, aunque se muestre anónimo |
| `letter_id` | uuid (FK), nullable | Si comparte una carta real |
| `letter_delivery_id` | uuid, nullable | Si comparte una carta **recibida** |
| `type` | enum | `shared_letter`, `unaddressed_letter`, `poem`, `reflection` |
| `title` | string, nullable | |
| `body` | text | |
| `excerpt` | string | |
| `visibility` | enum | `public`, `unlisted`, `followers` (futuro) |
| `is_anonymous` | boolean | Muestra `pen_name` o "Anónimo" |
| `testimonial` | text, nullable | Opinión sobre quien la envió |
| `sender_consent_status` | enum | `not_required`, `pending`, `granted`, `denied` |
| `comments_enabled` | boolean | |
| `reactions_count` / `comments_count` | integer | Contadores desnormalizados |
| `moderation_status` | enum | `pending`, `approved`, `flagged`, `rejected` |
| `published_at` | timestamp, nullable | |
| `slug` | string, unique | |

> **Regla de privacidad clave:** publicar una carta *recibida* expone el texto de otra persona.
> `sender_consent_status` obliga a pedir permiso al autor original antes de hacerla pública, salvo
> que la carta fuese anónima (en cuyo caso no hay identidad que proteger, pero sí autoría del texto:
> por defecto exige consentimiento igualmente).

#### `comments`

| Campo | Tipo |
| --- | --- |
| `id` | uuid (PK) |
| `public_post_id` | uuid (FK) |
| `author_id` | uuid (FK) |
| `parent_id` | uuid, nullable (respuestas de 1 nivel) |
| `body` | text |
| `is_anonymous` | boolean |
| `moderation_status` | enum |
| `created_at` / `deleted_at` | |

#### `reactions`

Polimórfica sobre `public_posts` y `comments`.

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid | |
| `user_id` | uuid | |
| `reactable_type` / `reactable_id` | morph | |
| `type` | enum | `heart`, `tear`, `flower`, `candle` — nada de "me gusta" genérico |

Unique: `(user_id, reactable_type, reactable_id, type)`.

#### `doll_requests`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `client_id` | uuid (FK) | |
| `doll_id` | uuid (FK users), nullable | `null` si es solicitud abierta al pool |
| `status` | enum | `pending`, `accepted`, `in_progress`, `awaiting_client`, `completed`, `cancelled`, `rejected`, `expired` |
| `occasion` | string | "Disculpa a un hermano" |
| `brief_notes` | text | Contexto que da el cliente |
| `target_recipient_hint` | string, nullable | Sin datos personales del tercero |
| `desired_tone` | jsonb | |
| `deadline_at` | timestamp, nullable | |
| `price_amount` / `currency` | integer/char(3), nullable | |
| `payment_status` | enum, nullable | `not_required`, `pending`, `held`, `released`, `refunded` |
| `final_letter_id` | uuid, nullable (FK letters) | Borrador entregado al cliente |
| `accepted_at` / `started_at` / `completed_at` / `closed_at` | timestamps | |
| `expires_at` | timestamp | Auto-expira si la doll no responde |
| `rating` | tinyint, nullable | 1-5 |
| `review` | text, nullable | |

#### `doll_chat_messages`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid (PK) | |
| `doll_request_id` | uuid (FK) | |
| `sender_id` | uuid (FK) | |
| `type` | enum | `text`, `draft`, `system`, `attachment` |
| `body` | text, nullable (cifrado) | |
| `draft_payload` | jsonb, nullable | Versión del borrador |
| `attachment_path` | string, nullable | |
| `read_at` | timestamp, nullable | |
| `created_at` | timestamp | |

Índice: `(doll_request_id, created_at)`.

#### `blocks`

| Campo | Tipo |
| --- | --- |
| `id` | uuid |
| `blocker_id` | uuid (FK) |
| `blocked_id` | uuid (FK) |
| `reason` | string, nullable |
| `created_at` | timestamp |

Unique `(blocker_id, blocked_id)`. Consultado en: envío directo, asignación aleatoria, comentarios,
solicitudes a Dolls.

#### `reports`

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | uuid | |
| `reporter_id` | uuid (FK) | |
| `reportable_type` / `reportable_id` | morph | letter_delivery, public_post, comment, user, doll_chat_message |
| `category` | enum | `harassment`, `sexual`, `hate`, `violence`, `self_harm`, `spam`, `minor_safety`, `other` |
| `details` | text, nullable | |
| `status` | enum | `open`, `reviewing`, `actioned`, `dismissed` |
| `severity` | enum | `low`, `medium`, `high`, `critical` |
| `handled_by` | uuid, nullable | |
| `resolution_note` | text, nullable | |
| `handled_at` | timestamp, nullable | |

#### `moderation_actions`

Registro de acciones tomadas (auditoría legal).

| Campo | Tipo |
| --- | --- |
| `id`, `moderator_id`, `target_type`, `target_id` | |
| `action` | enum: `warn`, `hide_content`, `delete_content`, `suspend_user`, `ban_user`, `restrict_random`, `no_action` |
| `reason`, `expires_at`, `created_at` | |

#### `push_subscriptions`

| Campo | Tipo |
| --- | --- |
| `id`, `user_id` | |
| `platform` | enum: `web`, `ios`, `android` |
| `endpoint` (web) / `device_token` (móvil) | |
| `public_key`, `auth_token` | web push |
| `device_name`, `last_used_at` | |

#### `notifications`

Tabla estándar de Laravel (`php artisan notifications:table`), con `type`, `notifiable`, `data` jsonb,
`read_at`.

#### `transit_routes` (opcional, sabor)

Catálogo de "rutas postales" ficticias para el tracking: `name`, `min_minutes`, `max_minutes`,
`waypoints` jsonb.

---

## 7. Máquinas de estado

### 7.1 `letter_deliveries`

```
                    ┌──────────┐
   crear envío ───► │  queued  │
                    └────┬─────┘
          cancelar ◄─────┤
                         │ scheduled_for <= now()  (DispatchDueLettersJob)
                         ▼
                   ┌────────────┐
                   │ in_transit │───► failed (destinatario inexistente/suspendido)
                   └─────┬──────┘───► blocked (el destinatario bloqueó al remitente)
                         │ delivered_at <= now()  (DeliverArrivedLettersJob)
                         ▼
                   ┌───────────┐
                   │ delivered │
                   └─────┬─────┘
                         │ el destinatario abre la carta
                         ▼
                     ┌──────┐
                     │ read │
                     └──────┘
```

Reglas:

- `queued → cancelled`: permitido solo por el remitente.
- `in_transit → cancelled`: permitido dentro de una ventana de gracia configurable
  (`config('postal.grace_period_minutes')`, sugerido 15). Después, la carta ya "salió" y no vuelve.
- `delivered` es irreversible. El remitente no puede borrar el contenido del buzón ajeno.
- `blocked`: la entrega se marca así silenciosamente. **El remitente ve "entregada"**, no se le informa
  del bloqueo (patrón estándar anti-acoso).
- Toda transición escribe en `delivery_events`.

Implementación sugerida: métodos explícitos en el modelo (`markDispatched()`, `markDelivered()`) que
validan el estado origen y son atómicos, no `update()` suelto en controladores.

### 7.2 `doll_requests`

```
pending ──accept──► accepted ──start──► in_progress ⇄ awaiting_client
   │                                          │
   │ reject                                   │ complete
   ▼                                          ▼
rejected                                  completed ──► closed
   │
   │ sin respuesta antes de expires_at
   ▼
expired
```

- `pending → expired`: job programado que revisa `expires_at` (sugerido 48 h).
- El canal de chat se abre en `in_progress` y pasa a solo-lectura en `completed`/`cancelled`.
- `completed` copia el borrador final a `letters` con `author_id = client_id` y
  `doll_request_id` referenciado (crédito a la Doll, propiedad del cliente).
- Cliente y Doll pueden cancelar antes de `in_progress` sin penalización.

### 7.3 `moderation_status` (cartas y posts)

```
pending ──filtro automático limpio──► approved
   │
   ├──filtro detecta patrón──► flagged ──revisión humana──► approved | rejected
   └──patrón crítico────────► rejected (bloqueo inmediato, reporte automático)
```

Para **cartas dirigidas entre usuarios que ya se conocen**, la moderación puede ser reactiva (solo por
reporte). Para **cartas aleatorias y contenido del blog**, debe ser proactiva (siempre pasa por filtro
antes de despachar/publicar). Esta distinción es la que hace viable el módulo aleatorio.

---

## 8. Módulos funcionales

### 8.1 Usuarios y perfiles

**Alcance**

- Registro con email + contraseña, verificación obligatoria por correo.
- OAuth (Google/Apple) en fase 2 — Apple es obligatorio si publicas en App Store con otros logins.
- Perfil público mínimo: nombre o seudónimo, avatar, bio, país, año de registro. **Nunca email.**
- Ajustes: idioma, zona horaria, tema, notificaciones, horas de silencio.
- Opt-in a cartas aleatorias, con explicación clara de lo que implica.
- Desactivación de cuenta (reversible) y eliminación (irreversible, con periodo de gracia de 30 días).
- Directorio de usuarios: **no debe existir un buscador abierto por nombre**. El descubrimiento se
  hace por código de amistad / enlace de invitación / handle exacto. Un directorio abierto convierte
  la plataforma en un canal de acoso.

**Handle de correspondencia**

Cada usuario tiene un `postal_handle` único e irrepetible (ej. `violet-e4f2`) que puede compartir para
recibir cartas. Es el equivalente a dar tu dirección postal. Se puede regenerar, invalidando el
anterior (lo que corta el flujo de un acosador sin necesidad de bloquear uno a uno).

> Este campo va en `users`: `postal_handle` string unique, `postal_handle_rotated_at` timestamp.

### 8.2 Composición de la carta

**Editor**

- Tiptap con extensiones limitadas: negrita, cursiva, subrayado, cita, salto de párrafo, separador
  ornamental. **Sin** tablas, sin colores libres, sin tamaños arbitrarios. La restricción es estética.
- Autoguardado del borrador cada 5 s con debounce → `PATCH /letters/{id}` (o IndexedDB si está offline).
- Contador de palabras y tiempo de lectura estimado.
- Selector de estética: papel, tinta, tipografía, sello de lacre, estampilla, orla.
- Vista previa a página completa antes de enviar ("leer como la leerá quien la reciba").
- Modo máquina de escribir opcional: sonido, sin corrector, sin borrar hacia atrás (guiño al anime).

**Persistencia del contenido**

- `body` cifrado en reposo con `Crypt::encryptString` (cast `encrypted` de Eloquent).
- `body_plain` se genera solo si el contenido debe pasar por moderación automática, y se purga tras
  la revisión salvo en cartas aleatorias/públicas.
- Sanitización obligatoria del HTML en servidor (`mews/purifier` o similar) con lista blanca de tags.
  Nunca confíes en la sanitización del cliente.

**Límites**

| Regla | Valor sugerido |
| --- | --- |
| Longitud máxima | 20 000 caracteres |
| Borradores simultáneos | 50 |
| Adjuntos por carta | 3 |
| Destinatarios por carta (multi-envío) | 10 |

### 8.3 Envío y simulación de tránsito postal

**Flujo de envío directo**

1. `POST /letters/{id}/send` con `{recipients:[...], scheduled_for, is_anonymous, ...}`.
2. Validaciones: destinatario existe, activo, no ha bloqueado al remitente, cuota diaria del remitente
   no superada, carta pasa el filtro de moderación si aplica.
3. Se crea una fila en `letter_deliveries` por destinatario con `status = queued`.
4. `letters.is_locked = true`. A partir de aquí el contenido es inmutable.
5. Se emite `LetterQueued`.

**Cálculo del tránsito**

```php
// app/Services/Postal/TransitCalculator.php
public function minutesFor(User $sender, User $recipient, ?string $tier = null): int
{
    $base = match ($tier ?? 'standard') {
        'express'  => [30, 120],      // 0.5 – 2 h
        'standard' => [120, 720],     // 2 – 12 h
        'slow'     => [1440, 4320],   // 1 – 3 días
    };

    // Modificador por "distancia" simulada entre países
    $distanceFactor = $this->geoFactor($sender->country_code, $recipient->country_code); // 1.0 – 2.5

    // Jitter para que no se sienta determinista
    $minutes = random_int($base[0], $base[1]) * $distanceFactor;

    // Eventos ambientales opcionales: tormenta, festivo postal
    $minutes *= $this->weatherFactor();

    return (int) round($minutes);
}
```

Reglas:

- El tránsito se calcula **en el momento del despacho**, no al programar. Así el retraso es real.
- `estimated_delivery_at` que se muestra al remitente lleva ±20 % de ruido respecto a `delivered_at`
  real, para que la sorpresa se mantenga.
- **Nunca menos de 30 minutos.** Si el tránsito es instantáneo, el producto se convierte en un chat.
- Las cartas urgentes/express deberían ser un recurso limitado (ej. 3 al mes), no un ajuste libre.
- Para envíos programados a fecha específica, el usuario elige **cuándo debe llegar**, y el sistema
  calcula hacia atrás el `scheduled_for` restando el tránsito estimado. Es más intuitivo:
  "quiero que llegue el 12 de abril a las 9:00".

**Días festivos postales**

Tabla opcional `postal_holidays` (`date`, `name`, `multiplier`). Un 25 de diciembre las cartas tardan
más. Detalle barato, muy alto retorno narrativo.

### 8.4 Programación y envíos recurrentes

**Caso de uso principal:** "una carta para cada cumpleaños de mi hija durante los próximos 10 años".

**Diseño**

- El usuario crea un `letter_schedule` con `recurrence_type = yearly`, `anchor_date`,
  `occurrences_total = 10`.
- Opción A (una sola carta repetida): `letter_id` fijo.
- Opción B (una carta distinta por año): `letters_map` con la fecha como clave. La UI presenta una
  línea de tiempo donde el usuario va escribiendo cada carta; puede dejar huecos y completarlos después.
- **Generación perezosa:** no crees 10 filas de `letter_deliveries` de golpe. Genera las ocurrencias
  con una ventana deslizante de 90 días (`GenerateUpcomingDeliveriesJob` diario). Ventajas: cambiar el
  contenido de la carta del año 7 no obliga a regenerar nada, y no llenas la tabla de filas muertas.
  Excepción: si la carta es "para cuando yo no esté", genera todo y congela.

**Zonas horarias y fechas límite**

```php
$target = CarbonImmutable::parse($schedule->anchor_date)
    ->setTimeFromTimeString($schedule->local_time)
    ->setTimezone($schedule->timezone)   // congelada al crear
    ->addYears($i)
    ->utc();
```

Casos a manejar explícitamente:

- **29 de febrero:** si el año destino no es bisiesto, entrega el 28 de febrero (configurable: 1 de marzo).
- **Cambio de horario de verano:** al fijar la hora en zona local y convertir a UTC en el momento de
  la generación, el problema se resuelve solo. No precalcules UTC a 10 años vista.
- **El usuario cambia de zona horaria:** el schedule conserva la zona original. Avísale en la UI y
  ofrécele recalcular.

**Cartas póstumas / "para cuando yo no esté"**

Es una petición inevitable en un producto así. Diseño mínimo:

- `schedule.trigger_type = 'date' | 'inactivity'`.
- Con `inactivity`: si el usuario no inicia sesión durante N meses, se le envían 3 avisos por correo
  espaciados; si no responde, se despachan las cartas.
- Requiere aviso legal claro y confirmación doble. **No lo lances en fase 1**, pero deja el campo.

### 8.5 Buzón y lectura

- El buzón es `letter_deliveries` donde `recipient_id = auth()->id()` y
  `status IN ('delivered','read')`. **Nunca expongas entregas `in_transit` al destinatario.**
- Estados visuales: cerrada (sin abrir, con lacre intacto) / abierta / archivada / guardada en favoritos.
- Abrir una carta es una acción explícita (`POST /mailbox/{delivery}/open`) que:
  - fija `read_at`,
  - cambia el estado a `read`,
  - emite `LetterRead`,
  - notifica al remitente **solo si el destinatario lo permite** (`user_settings.share_read_receipts`).
- Animación de apertura del sobre: es el momento clave de la experiencia. Precarga el contenido pero
  no lo muestres hasta terminar la animación (respetando `prefers-reduced-motion`).
- Responder una carta crea un nuevo borrador con `in_reply_to_delivery_id` (añade este campo a
  `letters`), lo que permite construir hilos de correspondencia.
- Exportar carta a PDF (fase 2) con la estética elegida — muy solicitado y fácil con `spatie/laravel-pdf`.

### 8.6 "Botella al mar" — cartas aleatorias

Este es el módulo con mayor riesgo. Diseño defensivo:

**Requisitos para ser candidato a receptor**

```php
$candidates = User::query()
    ->where('id', '!=', $sender->id)
    ->where('status', 'active')
    ->whereNotNull('email_verified_at')
    ->where('accepts_random_letters', true)
    ->where('last_active_at', '>=', now()->subDays(30))
    ->whereNotExists(fn ($q) => $q->from('blocks')
        ->whereColumn('blocks.blocker_id', 'users.id')
        ->where('blocks.blocked_id', $sender->id))
    ->whereRaw('(
        select count(*) from letter_deliveries d
        where d.recipient_id = users.id
          and d.delivery_mode = \'random\'
          and d.created_at >= ?
    ) < users.random_letters_daily_cap', [now()->startOfDay()])
    ->whereNotIn('id', $recentRecipientsOfThisSender);   // no repetir en 90 días
```

**Selección eficiente (evita `inRandomOrder()` sobre toda la tabla)**

Mantén un *pool* en Redis: un `SET` de IDs elegibles refrescado cada 15 minutos por un job. La
selección es `SRANDMEMBER` + verificación puntual en base de datos. Coste O(1) frente a un full scan.

**Controles obligatorios**

| Control | Regla |
| --- | --- |
| Cuota de emisión | Máx. 3 cartas aleatorias por usuario y día, 10 por semana |
| Antigüedad mínima | Cuenta con más de 7 días y email verificado |
| Moderación | **Siempre** pasa por filtro automático antes de encolar |
| Anonimato | Por defecto el remitente aparece como seudónimo, no como perfil real |
| Sin respuesta directa | El receptor puede responder **una vez** por el mismo canal anónimo; para pasar a correspondencia abierta, ambos deben aceptar |
| Bloqueo | Un bloqueo cancela cualquier entrega aleatoria pendiente entre ese par |
| Reporte con consecuencia | 2 reportes confirmados → `restrict_random` automático |
| Sin adjuntos | Las cartas aleatorias no permiten imágenes (vector de abuso) |

**Reciprocidad opcional**

Idea de producto: para enviar una botella al mar debes haber leído (y opcionalmente respondido) la
última que recibiste. Aumenta la calidad del ecosistema y reduce el spam.

### 8.7 Blog y cartas al vacío

**Tipos de publicación**

1. `shared_letter` — carta recibida que el destinatario publica junto a su testimonio.
   → Requiere `sender_consent_status = granted`. Flujo: el destinatario solicita permiso, el remitente
   recibe una notificación con vista previa exacta de lo que se publicará, y acepta o rechaza.
2. `unaddressed_letter` — carta dirigida a nadie (o a alguien que ya no está). Se publica directamente.
3. `poem` — micro-publicación literaria.
4. `reflection` — texto libre, comentario sobre la experiencia.

**Funcionalidades**

- Feed cronológico + feed "destacados" (curado manualmente, no algorítmico: encaja con el tono).
- Etiquetas temáticas (`duelo`, `amor`, `perdón`, `gratitud`, `despedida`), máximo 3 por post.
- Reacciones no numéricas y sin ranking público (evita la dinámica de red social).
- Comentarios anidados a 1 nivel, con opción de desactivar por post.
- Publicación anónima con `pen_name`; la autoría real se guarda siempre para moderación.
- Búsqueda full-text (Postgres `tsvector` en fase 1, Meilisearch en fase 2).
- Feed RSS/Atom público — barato y coherente con la estética.
- Colecciones/antologías: agrupar posts propios en un "libro" (fase 3).

**Regla de moderación:** todo post pasa por filtro automático; lo marcado se retiene hasta revisión.
Los temas de este producto (duelo, ruptura, enfermedad) rozan constantemente con detección de
autolesión: el filtro debe distinguir entre expresión legítima de dolor y riesgo real. Ante detección
de riesgo, la respuesta correcta no es borrar sino **mostrar recursos de ayuda al autor** y escalar a
revisión humana.

### 8.8 Auto Memory Dolls

**Concepto:** contratar a alguien para que te ayude a escribir una carta. Es la única excepción a la
regla "todo se comunica por cartas".

**Flujo completo**

```
1. Cliente explora el directorio de Dolls (filtros: especialidad, idioma, tarifa, disponibilidad)
2. Cliente envía DollRequest con brief (ocasión, tono, contexto, deadline)
3. Doll recibe notificación → acepta o rechaza antes de expires_at (48 h)
4. Al aceptar: status = accepted → se abre el canal privado al pasar a in_progress
5. Chat en tiempo real: la Doll pregunta, el cliente responde, la Doll envía borradores
6. Cada borrador es un doll_chat_message de tipo `draft` con draft_payload versionado
7. El cliente aprueba un borrador → status = completed
8. El sistema crea una Letter con author_id = cliente, doll_request_id = solicitud
9. El canal pasa a solo lectura (histórico consultable 90 días, luego se purga)
10. El cliente puede valorar a la Doll (1-5 + reseña); actualiza rating_avg
11. El cliente envía la carta por el flujo normal
```

**Aislamiento del canal**

```php
// routes/channels.php
Broadcast::channel('doll-request.{requestId}', function (User $user, string $requestId) {
    $request = DollRequest::find($requestId);

    return $request
        && in_array($request->status, ['in_progress', 'awaiting_client'], true)
        && in_array($user->id, [$request->client_id, $request->doll_id], true);
});
```

Refuerzo en la API: el middleware del controlador de mensajes repite la comprobación. Nunca confíes
solo en la autorización del canal de broadcast.

**Salvaguardas del rol Doll**

- Verificación manual antes de activar el rol (`doll_profiles.verified_at`).
- La Doll no ve el buzón, ni el historial, ni los contactos del cliente. Solo el brief y el chat.
- Prohibido intercambiar datos de contacto externos → filtro de patrones (emails, teléfonos, @handles)
  en los mensajes del chat, con aviso. Protege a ambas partes y protege tu modelo de negocio.
- Máximo de solicitudes concurrentes por Doll (`max_concurrent_requests`).
- Tiempo de respuesta e índice de finalización visibles en el perfil.
- Canal de reporte dentro del chat.
- La Doll no puede iniciar contacto: siempre responde a una solicitud.

**Pagos (fase 2)**

- Stripe Connect (Express) para pagar a las Dolls.
- El pago se retiene al aceptar (`payment_status = held`) y se libera al completar (`released`).
- Comisión de plataforma configurable.
- Tabla `payouts` y `transactions` con `stripe_payment_intent_id`, `amount`, `fee`, `net`, `status`.
- Alternativa fase 1: Dolls voluntarias, `rate_type = free`. Elimina toda la complejidad legal y
  fiscal, y permite validar el módulo antes de invertir en pagos.

### 8.9 Notificaciones

**Canales:** database (campana in-app), mail, web push (VAPID), FCM/APNs (móvil).

| Evento | In-app | Email | Push |
| --- | --- | --- | --- |
| Carta llegó al buzón | ✔ | ✔ | ✔ |
| Carta que enviaste fue entregada | ✔ | – | opcional |
| Carta que enviaste fue leída (si hay consentimiento) | ✔ | – | opcional |
| Envío programado despachado | ✔ | – | – |
| Nueva solicitud de Doll | ✔ | ✔ | ✔ |
| Mensaje en chat de Doll | ✔ | – | ✔ |
| Solicitud aceptada/rechazada | ✔ | ✔ | ✔ |
| Comentario en tu post | ✔ | opcional | opcional |
| Petición de consentimiento para publicar tu carta | ✔ | ✔ | ✔ |
| Aviso de inactividad (cartas póstumas) | ✔ | ✔ | ✔ |
| Acción de moderación sobre tu contenido | ✔ | ✔ | – |

Reglas:

- Respeta `quiet_hours` en hora local: si cae dentro, encola la notificación push hasta la hora de fin.
- Agrupa: si llegan 3 cartas en 10 minutos, una sola notificación ("Tienes 3 cartas nuevas").
- Toda notificación por email lleva enlace de gestión de preferencias y `List-Unsubscribe`.
- **La notificación de llegada nunca revela el contenido ni el remitente** si la carta es anónima.

### 8.10 Moderación, reportes y seguridad de la comunidad

**Capas**

1. **Preventiva:** opt-in a cartas aleatorias, verificación de email, antigüedad mínima, cuotas.
2. **Automática:** filtro en el momento del envío/publicación.
   - Listas de términos por categoría (configurable, versionado en base de datos, no hardcoded).
   - Detección de PII (emails, teléfonos, direcciones) en cartas aleatorias y chats de Doll.
   - Detección de enlaces y dominios en lista negra.
   - Opcional: clasificador externo (OpenAI Moderation, Perspective API, o un modelo propio) mediante
     un job asíncrono. Diseña la interfaz `ContentModerator` para poder cambiar de proveedor.
3. **Reactiva:** botón de reporte en carta, post, comentario, mensaje y perfil.
4. **Humana:** panel de moderación con cola priorizada por `severity`.

**Panel de moderación (Filament recomendado)**

- Cola de reportes con filtros y SLA visible.
- Vista del contenido reportado con contexto (conversación completa, historial del usuario).
- Acciones: descartar, ocultar, eliminar, advertir, restringir aleatorias, suspender, banear.
- Todo queda en `moderation_actions`.
- Métricas: reportes por 1000 cartas, tiempo medio de resolución, reincidencia.

**Escalado crítico:** las categorías `minor_safety` y `self_harm` con severidad `critical` deben
generar una alerta inmediata (email/Slack al equipo), no esperar en la cola.

**Contenido sensible y bienestar**

- Si el filtro detecta ideación autolesiva en una carta **saliente**, no la bloquees en silencio:
  muestra al autor recursos de ayuda de su país (según `country_code`) y permite continuar. Bloquear
  la expresión del dolor es contraproducente en este producto.
- Si se detecta en una carta **entrante aleatoria**, retén para revisión humana antes de entregarla a
  un desconocido.
- Mantén una tabla `support_resources` (`country_code`, `name`, `phone`, `url`) actualizable.

### 8.11 Administración

Panel con Filament v3 sobre la misma base de datos (fuera de la API pública):

- Gestión de usuarios, roles, suspensiones.
- Verificación de Dolls.
- Cola de moderación.
- Catálogos: estilos de papel, sellos, estampillas, rutas de tránsito, festivos postales.
- Métricas: cartas enviadas/entregadas/leídas, tasa de apertura, tiempo medio de tránsito, solicitudes
  de Doll por estado, salud de las colas.
- Feature flags (ver §16).
- Consulta de una entrega concreta con su timeline de `delivery_events` (soporte al usuario).

---

## 9. Colas, jobs y tareas programadas

### 9.1 Definición de colas

| Cola | Prioridad | Contenido |
| --- | --- | --- |
| `critical` | máxima | Moderación crítica, alertas de seguridad |
| `postal` | alta | Despacho y entrega de cartas |
| `notifications` | media | Push, email, database |
| `moderation` | media | Filtros automáticos, clasificadores |
| `default` | baja | Miscelánea |
| `maintenance` | mínima | Purgas, recálculos, métricas |

Horizon: workers separados por cola, con `balance: auto` y `maxProcesses` diferenciados.

### 9.2 Scheduler

```php
// routes/console.php (Laravel 11+)
Schedule::job(new DispatchDueLettersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new DeliverArrivedLettersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new RefreshRandomRecipientPoolJob)->everyFifteenMinutes();
Schedule::job(new GenerateUpcomingDeliveriesJob)->dailyAt('03:00');
Schedule::job(new ExpireStaleDollRequestsJob)->hourly();
Schedule::job(new SendQueuedQuietHourPushJob)->everyTenMinutes();
Schedule::job(new PurgeOldDollChatsJob)->dailyAt('04:00');
Schedule::job(new ProcessAccountDeletionsJob)->dailyAt('04:30');
Schedule::job(new CheckInactiveUsersForPosthumousJob)->dailyAt('05:00');
Schedule::job(new RecalculateDollRatingsJob)->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('model:prune')->daily();
```

### 9.3 Despacho de cartas — implementación correcta

El bug del `chunkById` original se evita procesando por lotes marcados:

```php
// app/Jobs/Postal/DispatchDueLettersJob.php
class DispatchDueLettersJob implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 300;

    public function handle(TransitCalculator $transit): void
    {
        $batchId = (string) Str::uuid();

        // 1) Reservar un lote de forma atómica.
        $claimed = LetterDelivery::query()
            ->where('status', DeliveryStatus::Queued)
            ->where('scheduled_for', '<=', now())
            ->whereNull('dispatch_batch_id')
            ->limit(500)
            ->update(['dispatch_batch_id' => $batchId]);

        if ($claimed === 0) {
            return;
        }

        // 2) Procesar solo lo reservado: el conjunto ya no cambia.
        LetterDelivery::where('dispatch_batch_id', $batchId)
            ->with(['letter', 'sender', 'recipient'])
            ->cursor()
            ->each(fn (LetterDelivery $d) => DispatchSingleLetterJob::dispatch($d->id));
    }
}
```

`DispatchSingleLetterJob` (idempotente):

```php
public function handle(TransitCalculator $transit): void
{
    $delivery = LetterDelivery::lockForUpdate()->find($this->deliveryId);

    if (! $delivery || $delivery->status !== DeliveryStatus::Queued) {
        return;   // ya procesada: salida silenciosa
    }

    // Resolver destinatario si es aleatoria
    if ($delivery->delivery_mode === DeliveryMode::Random && ! $delivery->recipient_id) {
        $recipient = app(RandomRecipientPicker::class)->pick($delivery->sender);
        if (! $recipient) {
            $delivery->update(['status' => DeliveryStatus::Failed, 'failure_reason' => 'no_recipient']);
            return;
        }
        $delivery->recipient_id = $recipient->id;
    }

    // Comprobaciones de última hora
    if ($delivery->recipient->hasBlocked($delivery->sender)) {
        $delivery->update(['status' => DeliveryStatus::Blocked, 'delivered_at' => now()]);
        return;   // el remitente verá "entregada"
    }

    if ($delivery->recipient->status !== UserStatus::Active) {
        $delivery->update(['status' => DeliveryStatus::Failed, 'failure_reason' => 'recipient_inactive']);
        LetterFailedNotification::send($delivery->sender, $delivery);
        return;
    }

    $minutes = $transit->minutesFor($delivery->sender, $delivery->recipient);

    $delivery->update([
        'status'                    => DeliveryStatus::InTransit,
        'dispatched_at'             => now(),
        'transit_duration_minutes'  => $minutes,
        'delivered_at'              => now()->addMinutes($minutes),   // objetivo, no confirmación
        'estimated_delivery_at'     => now()->addMinutes((int) ($minutes * random_int(80, 120) / 100)),
    ]);

    DeliveryEvent::record($delivery, 'dispatched', ['transit_minutes' => $minutes]);
    event(new LetterDispatched($delivery));
}
```

> **Nota de diseño:** `delivered_at` se usa aquí como *fecha objetivo* mientras el estado es
> `in_transit`, y se confirma cuando pasa a `delivered`. Si prefieres claridad absoluta, añade
> `arrives_at` (objetivo) y deja `delivered_at` solo para la confirmación real. Recomendado en
> proyectos que vayan a crecer.

`DeliverArrivedLettersJob` sigue el mismo patrón de reserva por lote, filtrando
`status = in_transit AND delivered_at <= now()`.

### 9.4 Reintentos y fallos

- `tries = 3`, `backoff = [60, 300, 900]` en jobs postales.
- `failed_jobs` monitorizado; alerta si crece por encima de un umbral.
- Un job fallido nunca debe dejar una entrega en estado intermedio: usa transacciones y estado
  explícito `failed` con `failure_reason`.
- `Horizon::routeSlackNotificationsTo(...)` para fallos en la cola `critical`.

---

## 10. Tiempo real (WebSockets)

### 10.1 Canales

| Canal | Tipo | Uso |
| --- | --- | --- |
| `private-user.{userId}` | privado | Notificaciones personales: llegada de carta, cambios de estado |
| `private-doll-request.{requestId}` | privado | Chat cliente ↔ Doll |
| `presence-doll-request.{requestId}` | presencia | Indicador "está escribiendo" y en línea |

**No existe** un canal público de cartas ni un canal de mensajería general entre usuarios. Es una
decisión de producto deliberada.

### 10.2 Eventos emitidos

| Evento | Canal | Payload |
| --- | --- | --- |
| `LetterArrived` | `user.{recipientId}` | id de entrega, estilo del sobre, remitente si no es anónima |
| `LetterDelivered` | `user.{senderId}` | id de entrega, momento |
| `LetterRead` | `user.{senderId}` | solo si hay consentimiento |
| `DollRequestUpdated` | `user.{clientId}`, `user.{dollId}` | nuevo estado |
| `DollChatMessageSent` | `doll-request.{id}` | mensaje |
| `DollDraftShared` | `doll-request.{id}` | versión del borrador |
| `ModerationActionTaken` | `user.{userId}` | acción y motivo |

### 10.3 Autenticación de Reverb

- PWA: cookie de sesión (Sanctum stateful), endpoint `/broadcasting/auth`.
- Móvil: token Bearer en el header de autorización de Echo.
- `routes/channels.php` valida siempre contra el estado actual de la base de datos, nunca contra un
  claim del cliente.

---

## 11. Diseño de la API

### 11.1 Convenciones

- Prefijo `/api/v1`. Toda ruptura de contrato genera `/api/v2`; ambas conviven durante la transición.
- JSON en request y response. `Accept: application/json` obligatorio.
- Respuestas mediante API Resources. Colecciones siempre paginadas (cursor pagination para feeds).
- Fechas en ISO-8601 UTC (`2026-04-12T09:00:00Z`). El cliente convierte a local.
- Nombres de recurso en plural y kebab-case en URL; snake_case en el cuerpo JSON.
- `Idempotency-Key` soportado en `POST /letters/{id}/send` y en pagos.

### 11.2 Formato de error

```json
{
  "message": "El destinatario no acepta cartas de este tipo.",
  "error_code": "RECIPIENT_NOT_ACCEPTING",
  "errors": { "recipient_id": ["No acepta cartas anónimas."] },
  "meta": { "request_id": "01J8..." }
}
```

`error_code` estable y documentado permite que la app móvil reaccione sin parsear textos traducibles.

### 11.3 Endpoints

**Auth**

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login                  (cookie SPA)
POST   /api/v1/auth/token                  (token móvil)
POST   /api/v1/auth/logout
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/email/verify/{id}/{hash}
POST   /api/v1/auth/email/resend
GET    /api/v1/auth/devices
DELETE /api/v1/auth/devices/{id}
```

**Perfil y ajustes**

```
GET    /api/v1/me
PATCH  /api/v1/me
POST   /api/v1/me/avatar
GET    /api/v1/me/settings
PATCH  /api/v1/me/settings
POST   /api/v1/me/postal-handle/rotate
POST   /api/v1/me/deactivate
DELETE /api/v1/me                          (inicia borrado con periodo de gracia)
GET    /api/v1/users/{handle}              (perfil público mínimo)
```

**Cartas**

```
GET    /api/v1/letters                     (borradores y enviadas, filtros por estado)
POST   /api/v1/letters                     (crear borrador)
GET    /api/v1/letters/{id}
PATCH  /api/v1/letters/{id}                (solo si !is_locked)
DELETE /api/v1/letters/{id}                (solo borradores)
POST   /api/v1/letters/{id}/attachments
DELETE /api/v1/letters/{id}/attachments/{attachmentId}
POST   /api/v1/letters/{id}/send           (directo o programado)
POST   /api/v1/letters/{id}/send-random    (botella al mar)
GET    /api/v1/letters/{id}/preview        (render con estilo aplicado)
GET    /api/v1/letters/styles              (catálogo de papel, tinta, sellos)
```

**Entregas y seguimiento**

```
GET    /api/v1/deliveries                  (mis envíos, con estado)
GET    /api/v1/deliveries/{id}
GET    /api/v1/deliveries/{id}/tracking    (timeline de delivery_events)
POST   /api/v1/deliveries/{id}/cancel
```

**Buzón**

```
GET    /api/v1/mailbox                     (?status=unread|read|archived, cursor pagination)
GET    /api/v1/mailbox/{deliveryId}
POST   /api/v1/mailbox/{deliveryId}/open
POST   /api/v1/mailbox/{deliveryId}/archive
POST   /api/v1/mailbox/{deliveryId}/favorite
POST   /api/v1/mailbox/{deliveryId}/reply  (crea borrador enlazado)
GET    /api/v1/mailbox/unread-count
```

**Programaciones**

```
GET    /api/v1/schedules
POST   /api/v1/schedules
GET    /api/v1/schedules/{id}
PATCH  /api/v1/schedules/{id}
DELETE /api/v1/schedules/{id}
GET    /api/v1/schedules/{id}/occurrences  (línea de tiempo con carta asignada o hueco)
PUT    /api/v1/schedules/{id}/occurrences/{date}/letter
POST   /api/v1/schedules/{id}/pause
POST   /api/v1/schedules/{id}/resume
```

**Blog**

```
GET    /api/v1/posts                       (?type=&tag=&sort=recent|featured)
POST   /api/v1/posts
GET    /api/v1/posts/{slug}
PATCH  /api/v1/posts/{id}
DELETE /api/v1/posts/{id}
POST   /api/v1/posts/{id}/reactions
DELETE /api/v1/posts/{id}/reactions/{type}
GET    /api/v1/posts/{id}/comments
POST   /api/v1/posts/{id}/comments
DELETE /api/v1/comments/{id}
GET    /api/v1/tags
POST   /api/v1/posts/{id}/request-consent  (pedir permiso al remitente original)
POST   /api/v1/consent-requests/{id}/respond
```

**Auto Memory Dolls**

```
GET    /api/v1/dolls                       (directorio con filtros)
GET    /api/v1/dolls/{id}
POST   /api/v1/doll-requests
GET    /api/v1/doll-requests               (mías: como cliente o como doll)
GET    /api/v1/doll-requests/{id}
POST   /api/v1/doll-requests/{id}/accept
POST   /api/v1/doll-requests/{id}/reject
POST   /api/v1/doll-requests/{id}/start
POST   /api/v1/doll-requests/{id}/complete
POST   /api/v1/doll-requests/{id}/cancel
POST   /api/v1/doll-requests/{id}/rate
GET    /api/v1/doll-requests/{id}/messages (paginado, orden ascendente)
POST   /api/v1/doll-requests/{id}/messages
POST   /api/v1/doll-requests/{id}/drafts   (la doll comparte un borrador)
POST   /api/v1/doll-requests/{id}/drafts/{draftId}/approve

// Perfil de Doll
POST   /api/v1/me/doll-profile             (solicitar el rol)
PATCH  /api/v1/me/doll-profile
POST   /api/v1/me/doll-profile/availability
```

**Seguridad de la comunidad**

```
GET    /api/v1/blocks
POST   /api/v1/blocks                      { user_id }
DELETE /api/v1/blocks/{userId}
POST   /api/v1/reports                     { reportable_type, reportable_id, category, details }
GET    /api/v1/support-resources           (?country_code=)
```

**Notificaciones**

```
GET    /api/v1/notifications
POST   /api/v1/notifications/{id}/read
POST   /api/v1/notifications/read-all
GET    /api/v1/notifications/unread-count
POST   /api/v1/push-subscriptions
DELETE /api/v1/push-subscriptions/{id}
GET    /api/v1/push/vapid-public-key
```

### 11.4 Rate limiting

```php
// app/Providers/AppServiceProvider.php
RateLimiter::for('api',        fn ($r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
RateLimiter::for('auth',       fn ($r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('send-letter',fn ($r) => Limit::perDay(30)->by($r->user()->id));
RateLimiter::for('send-random',fn ($r) => Limit::perDay(3)->by($r->user()->id));
RateLimiter::for('create-post',fn ($r) => Limit::perHour(5)->by($r->user()->id));
RateLimiter::for('comment',    fn ($r) => Limit::perHour(30)->by($r->user()->id));
RateLimiter::for('doll-chat',  fn ($r) => Limit::perMinute(30)->by($r->user()->id));
RateLimiter::for('report',     fn ($r) => Limit::perHour(10)->by($r->user()->id));
```

### 11.5 Documentación

- OpenAPI 3.1 generado con `dedoc/scramble` (anotaciones mínimas) o `l5-swagger`.
- Publicado en `/docs` en staging, protegido en producción.
- Colección de Postman/Bruno versionada en el repositorio para la app móvil.

---

## 12. Frontend PWA

### 12.1 Estructura de carpetas

```
src/
├── api/                 # clientes HTTP por dominio
│   ├── client.ts        # instancia axios + interceptores
│   ├── letters.ts
│   ├── mailbox.ts
│   ├── dolls.ts
│   └── posts.ts
├── assets/
│   ├── fonts/
│   ├── textures/        # papel, lacre
│   └── seals/
├── components/
│   ├── letter/          # LetterEditor, LetterPaper, WaxSeal, EnvelopeOpen
│   ├── mailbox/
│   ├── doll/
│   ├── blog/
│   └── ui/              # botones, inputs, modales
├── composables/         # useAuth, useEcho, useCountdown, useOfflineQueue
├── layouts/
├── locales/             # es.json, en.json
├── router/
│   ├── index.ts
│   └── guards.ts
├── stores/              # auth, letters, mailbox, dolls, notifications, ui
├── types/               # tipos generados desde OpenAPI
├── views/
└── main.ts
```

### 12.2 Rutas principales

| Ruta | Vista | Guard |
| --- | --- | --- |
| `/` | Landing / feed público | – |
| `/login`, `/register` | Auth | guest |
| `/escritorio` | Dashboard: borradores, en tránsito, buzón | auth |
| `/escribir` | Editor de carta nueva | auth, verified |
| `/escribir/:id` | Editar borrador | auth, owner |
| `/enviar/:id` | Flujo de envío (destinatario, fecha, estética) | auth, verified |
| `/buzon` | Buzón | auth |
| `/buzon/:deliveryId` | Lectura de carta (con animación de apertura) | auth |
| `/envios` | Mis envíos + seguimiento | auth |
| `/programaciones` | Lista de recurrencias | auth |
| `/programaciones/:id` | Línea de tiempo de ocurrencias | auth |
| `/botella` | Botella al mar | auth, verified |
| `/blog` | Feed público | – |
| `/blog/:slug` | Post | – |
| `/blog/nuevo` | Publicar | auth |
| `/dolls` | Directorio | auth |
| `/dolls/:id` | Perfil de Doll | auth |
| `/solicitudes` | Mis solicitudes | auth |
| `/solicitudes/:id` | Chat con la Doll | auth, participante |
| `/panel-doll` | Bandeja de la Doll | auth, role:doll |
| `/ajustes/*` | Perfil, notificaciones, privacidad, bloqueos, dispositivos | auth |

### 12.3 Estrategia offline (PWA)

| Recurso | Estrategia Workbox |
| --- | --- |
| App shell, JS, CSS, fuentes | `precache` (revisión por build) |
| Texturas e imágenes de estilos | `CacheFirst`, 30 días |
| `GET /mailbox`, `GET /mailbox/{id}` | `StaleWhileRevalidate` + espejo en IndexedDB |
| Borradores | IndexedDB local, fuente de verdad hasta sincronizar |
| Resto de `GET` | `NetworkFirst` con timeout de 3 s |
| `POST`/`PATCH` sin conexión | `BackgroundSync` queue, reintento al recuperar red |

**Cola offline de borradores:** el usuario debe poder escribir una carta completa sin conexión. Guarda
en IndexedDB con `local_id`; al reconectar, sincroniza y reconcilia con el `id` del servidor. Nunca
pierdas texto escrito: es lo más doloroso que puede pasar en este producto.

**Notificaciones push:** el Service Worker maneja `push` y `notificationclick` → abre
`/buzon/:deliveryId`.

**Requisitos de instalación:** manifest completo (iconos 192/512 + maskable), `display: standalone`,
`theme_color` acorde a la paleta, screenshots para el prompt de instalación.

### 12.4 Consideraciones para la futura app móvil

Decisiones que debes tomar **ahora** para no reescribir el backend después:

- Autenticación por token desde el día 1, aunque la PWA use cookies.
- `error_code` estable en las respuestas.
- Paginación por cursor (los offsets se rompen con listas que cambian).
- Endpoint de "sincronización delta": `GET /mailbox?updated_since=` para no descargar todo.
- Versionado forzado: header `X-App-Version`; el backend puede responder `426 Upgrade Required`.
- Registro de push agnóstico de plataforma (`push_subscriptions.platform`).
- Nada de HTML crudo dependiente del navegador en las respuestas: devuelve el JSON de Tiptap y deja
  que cada cliente renderice.

Ruta recomendada: **Capacitor** envolviendo la misma PWA para la v1 móvil (semanas, no meses), y app
nativa solo si la tracción lo justifica.

---

## 13. Seguridad y privacidad

### 13.1 Datos en reposo

- `letters.body` y `doll_chat_messages.body` cifrados con el cast `encrypted` de Laravel.
- Consecuencia importante: **no puedes hacer `WHERE body LIKE`**. Por eso existe `body_plain`, que solo
  se conserva mientras es necesario para moderación o búsqueda consentida.
- Rotación de `APP_KEY`: documenta el procedimiento (`php artisan key:rotate` con re-cifrado por lotes)
  antes de necesitarlo.
- Backups cifrados, diarios, con prueba de restauración trimestral. Un producto que promete guardar
  una carta durante 10 años no puede permitirse perder la base de datos.

### 13.2 Datos en tránsito

- HTTPS obligatorio, HSTS, TLS 1.2+.
- WSS para Reverb.
- CSP estricta en la PWA; nada de `unsafe-inline`.

### 13.3 Privacidad por diseño

- El email nunca aparece en respuestas públicas de la API.
- El perfil público es mínimo y no expone actividad.
- Sin buscador abierto de usuarios (§8.1).
- Anonimato real: cuando `is_anonymous = true`, el `sender_id` no viaja al cliente en ninguna
  respuesta. Fíltralo en el API Resource, no solo en la vista.
- Los recibos de lectura son opt-in.
- Las cartas en tránsito no son visibles para el destinatario ni aparecen en ningún conteo.

### 13.4 Retención, borrado y casos límite

Este es el apartado que más problemas te evitará más adelante.

| Situación | Comportamiento |
| --- | --- |
| Usuario desactiva su cuenta | Cartas en tránsito hacia él se pausan; las suyas programadas también |
| Usuario elimina su cuenta | Periodo de gracia de 30 días; luego anonimización |
| Anonimización | `users` conserva la fila con datos borrados (`name = "Usuario eliminado"`, email hash), para no romper las cartas ya entregadas a terceros |
| Cartas ya entregadas | **Permanecen en el buzón del destinatario.** Son suyas. Se muestra el remitente como "Usuario eliminado" |
| Cartas programadas del usuario eliminado | Se cancelan, salvo que estén marcadas explícitamente como póstumas y el usuario haya consentido su envío tras el borrado |
| Destinatario eliminado | Entregas pendientes → `failed`, se notifica al remitente y se le devuelve el contenido a borradores |
| Email rebotado permanentemente | Se desactivan notificaciones por correo, la cuenta se marca para revisión |
| Solicitud de exportación (RGPD) | `GET /me/export` genera un ZIP asíncrono con cartas, posts y datos de perfil; enlace firmado con caducidad de 24 h |
| Chats de Doll | Purga automática 90 días tras cerrar la solicitud (avísalo en los términos) |
| Reportes y acciones de moderación | Retención de 2 años, incluso tras el borrado de la cuenta (base legal: interés legítimo) |

### 13.5 Cumplimiento

- Términos de servicio y política de privacidad publicados antes del lanzamiento.
- Edad mínima 16 años (o 13 con consentimiento parental, según jurisdicción). **Verifícalo en el
  registro**: una plataforma de mensajes anónimos con menores es un riesgo serio.
- Consentimiento de cookies solo si añades analítica de terceros. Considera analítica sin cookies
  (Plausible, Umami) y te ahorras el banner.
- Registro de actividad de tratamiento y DPA con proveedores (correo, S3, Stripe).

---

## 14. Observabilidad, testing y CI/CD

### 14.1 Observabilidad

- **Errores:** Sentry en API y en la PWA, con `release` por commit.
- **Logs:** canal `stack` → stdout en JSON; agregación con Loki/Grafana o similar.
- **Colas:** Horizon + alertas por tiempo de espera y `failed_jobs`.
- **Uptime:** health check `GET /api/v1/health` que verifica base de datos, Redis, colas y Reverb.
- **Métricas de negocio:** cartas enviadas, tasa de entrega, tasa de apertura, tiempo medio hasta
  lectura, solicitudes de Doll completadas, reportes por 1000 cartas.
- **Alerta específica:** si `DispatchDueLettersJob` no procesa nada durante 15 minutos habiendo
  entregas vencidas, el reloj postal está roto. Es tu métrica más crítica.

### 14.2 Testing

**Backend (Pest)**

- Feature tests de cada endpoint: código de estado, forma del JSON, autorización.
- Tests de las máquinas de estado: cada transición válida e inválida.
- Tests de tiempo con `travel()`: programar una carta, viajar en el tiempo, verificar la entrega.
- Tests del selector aleatorio: bloqueos, cuotas, exclusión propia.
- Tests de policies: un usuario no puede leer el buzón de otro (el test más importante del proyecto).
- Cobertura objetivo: 70 % global, 90 % en `app/Services/Postal` y `app/Policies`.

**Frontend**

- Vitest para composables y stores.
- Playwright E2E: registro → escribir → enviar → (viaje temporal simulado en API de test) → leer.
- Lighthouse CI: PWA instalable, rendimiento > 90 en móvil.

### 14.3 CI/CD

```yaml
# .github/workflows/api.yml (esquema)
jobs:
  quality:
    - Pint --test
    - PHPStan (larastan nivel 6)
    - Pest con Postgres y Redis en servicios
  deploy:
    - Solo en main y con quality en verde
    - php artisan down --render=maintenance
    - composer install --no-dev -o
    - php artisan migrate --force
    - php artisan config:cache route:cache event:cache
    - php artisan horizon:terminate
    - php artisan up
```

Migraciones siempre compatibles hacia atrás (añadir columna → desplegar → migrar datos → eliminar
columna en un despliegue posterior). Con la app móvil en circulación, no puedes asumir que todos los
clientes se actualizan a la vez.

---

## 15. Roadmap por fases

### Fase 0 — Cimientos (1–2 semanas)

- [ ] Repos, docker-compose, CI básica
- [ ] Laravel API skeleton + Sanctum + CORS + estructura `/api/v1`
- [ ] Vue + Vite + Tailwind + Pinia + router con guards
- [ ] Auth completa: registro, login, verificación de email, recuperación
- [ ] Modelo `users` + `user_settings` + perfil + ajustes
- [ ] Sistema de diseño base (tokens, tipografía, componentes primitivos)

### Fase 1 — El correo funciona (3–4 semanas) — **MVP**

- [ ] Editor de cartas con estética y autoguardado
- [ ] `letters` + `letter_deliveries` + `delivery_events`
- [ ] Envío directo por `postal_handle`
- [ ] Simulación de tránsito + jobs de despacho y entrega + Horizon
- [ ] Buzón con animación de apertura
- [ ] Seguimiento postal para el remitente
- [ ] Notificaciones in-app y por email
- [ ] Bloqueos y reportes básicos
- [ ] PWA instalable con caché offline del buzón

> **Criterio de lanzamiento de fase 1:** dos personas pueden escribirse cartas que tardan en llegar.
> Ese es el producto. Todo lo demás es ampliación.

### Fase 2 — Tiempo y comunidad (3–4 semanas)

- [ ] `letter_schedules`: envíos programados y recurrentes con línea de tiempo
- [ ] Blog: publicaciones, tipos, etiquetas, comentarios, reacciones
- [ ] Flujo de consentimiento para publicar cartas recibidas
- [ ] Botella al mar con todos los controles de §8.6
- [ ] Moderación automática + panel Filament
- [ ] Web Push
- [ ] i18n es/en

### Fase 3 — Auto Memory Dolls (3–4 semanas)

- [ ] `doll_profiles`, directorio, verificación
- [ ] `doll_requests` con máquina de estados completa
- [ ] Reverb + chat privado + borradores versionados
- [ ] Valoraciones y reseñas
- [ ] Filtro anti-intercambio de contactos
- [ ] (Opcional) Stripe Connect y pagos retenidos

### Fase 4 — Móvil y refinamiento

- [ ] Capacitor sobre la PWA, push nativo (FCM/APNs)
- [ ] Exportación de cartas a PDF
- [ ] Sincronización delta
- [ ] Búsqueda con Meilisearch
- [ ] Cartas póstumas por inactividad
- [ ] Particionado de `letter_deliveries` si el volumen lo pide

---

## 16. Puntos de extensión futuros

El sistema está diseñado para que estas ideas se puedan añadir **sin refactorizar el núcleo**. Los
puntos de enganche son: eventos de dominio, `style` JSON, `metadata` JSON y la tabla de feature flags.

### 16.1 Feature flags

Tabla `feature_flags` (`key`, `enabled`, `rollout_percentage`, `payload` jsonb) o `laravel/pennant`.
Todo módulo nuevo nace detrás de un flag. Endpoint `GET /api/v1/features` que la PWA y la app móvil
consultan al arrancar: permite desactivar un módulo en producción sin desplegar.

### 16.2 Ideas compatibles con el modelo actual

| Idea | Enganche |
| --- | --- |
| Cartas con música / audio adjunto | `letter_attachments.type = 'audio'` |
| Sellos y papeles desbloqueables por logros | Catálogo + tabla `user_unlocked_styles` |
| Cartas colaborativas (varios remitentes) | Tabla pivote `letter_co_authors` |
| Correspondencia por hilos con vista de conversación | `letters.in_reply_to_delivery_id` (ya previsto) |
| Cartas a tu yo del futuro | `letter_deliveries` con `sender_id = recipient_id` |
| Cápsulas del tiempo grupales | `letter_schedules` con múltiples destinatarios |
| Suscripción premium (más express, papeles exclusivos) | Tabla `subscriptions` + Cashier |
| Intercambio de cartas por temas/intereses | Extender `RandomRecipientPicker` con matching por tags |
| Traducción automática de cartas | Job asíncrono + `letters.translations` jsonb |
| Impresión y envío físico real | Integración con API de impresión postal; nuevo `delivery_mode = 'physical'` |
| Diario privado (cartas a nadie, sin publicar) | `letters.kind = 'journal'`, sin entrega |
| Modo "carta perdida" — llega meses después | Solo un `transit_duration_minutes` extremo |
| Insignias de Doll y niveles | `doll_profiles.badges` jsonb |
| Eventos estacionales (Navidad, cartas de año nuevo) | `postal_holidays` + campañas |
| API pública para terceros | `/api/v1` ya versionada; añadir Passport + scopes |
| Webhooks salientes | Listeners sobre los eventos de dominio existentes |

### 16.3 Reglas para añadir un módulo nuevo

1. ¿Rompe la regla "todo se comunica por cartas"? Si sí, necesita una justificación tan fuerte como la
   de las Dolls.
2. ¿Introduce un canal de contacto no consentido? Entonces necesita opt-in, cuota y moderación.
3. ¿Añade estado a una entidad existente? Actualiza la máquina de estados de §7, no improvises campos.
4. ¿Se puede resolver con un listener sobre un evento existente? Prefiérelo antes que tocar el servicio.
5. Nace detrás de un feature flag y con tests de policy.

---

## 17. Anexos

### 17.1 Enums de PHP

```php
enum UserRole: string        { case Client = 'client'; case Doll = 'doll';
                               case Moderator = 'moderator'; case Admin = 'admin'; }

enum UserStatus: string      { case Active = 'active'; case Suspended = 'suspended';
                               case Deactivated = 'deactivated'; case Deleted = 'deleted'; }

enum LetterKind: string      { case Direct = 'direct'; case Random = 'random';
                               case Unaddressed = 'unaddressed'; case DollDraft = 'doll_draft'; }

enum DeliveryStatus: string  { case Queued = 'queued'; case InTransit = 'in_transit';
                               case Delivered = 'delivered'; case Read = 'read';
                               case Cancelled = 'cancelled'; case Failed = 'failed';
                               case Blocked = 'blocked'; }

enum DeliveryMode: string    { case Direct = 'direct'; case Random = 'random'; }

enum TransitTier: string     { case Express = 'express'; case Standard = 'standard';
                               case Slow = 'slow'; }

enum DollRequestStatus: string { case Pending = 'pending'; case Accepted = 'accepted';
                                 case InProgress = 'in_progress'; case AwaitingClient = 'awaiting_client';
                                 case Completed = 'completed'; case Cancelled = 'cancelled';
                                 case Rejected = 'rejected'; case Expired = 'expired'; }

enum PostType: string        { case SharedLetter = 'shared_letter';
                               case UnaddressedLetter = 'unaddressed_letter';
                               case Poem = 'poem'; case Reflection = 'reflection'; }

enum ModerationStatus: string { case Pending = 'pending'; case Approved = 'approved';
                                case Flagged = 'flagged'; case Rejected = 'rejected'; }

enum ReportCategory: string  { case Harassment = 'harassment'; case Sexual = 'sexual';
                               case Hate = 'hate'; case Violence = 'violence';
                               case SelfHarm = 'self_harm'; case Spam = 'spam';
                               case MinorSafety = 'minor_safety'; case Other = 'other'; }
```

### 17.2 Estructura de `letters.style`

```json
{
  "paper":   "parchment",
  "texture": "linen",
  "font":    "cormorant",
  "ink":     "sepia",
  "seal":    { "type": "wax", "color": "burgundy", "sigil": "violet" },
  "stamp":   "lavender_field",
  "border":  "art_nouveau_thin",
  "flourish": true
}
```

Catálogo servido por `GET /api/v1/letters/styles`, no hardcodeado en el frontend. Así puedes añadir un
papel nuevo sin desplegar el cliente ni la app móvil.

### 17.3 Ejemplo de payload de envío

```json
POST /api/v1/letters/{id}/send
{
  "recipients": [{ "postal_handle": "hana-8c21" }],
  "delivery": {
    "mode": "direct",
    "tier": "standard",
    "arrive_at": "2027-04-12T09:00:00Z",
    "arrive_timezone": "America/New_York"
  },
  "is_anonymous": false,
  "reveal_sender_at": null,
  "allow_read_receipt": true
}
```

Respuesta:

```json
{
  "data": [{
    "id": "01J8XK...",
    "status": "queued",
    "scheduled_for": "2027-04-12T02:47:00Z",
    "estimated_delivery_at": "2027-04-12T09:14:00Z",
    "recipient": { "display_name": "Hana", "postal_handle": "hana-8c21" }
  }]
}
```

### 17.4 Variables de entorno relevantes

```dotenv
APP_TIMEZONE=UTC
APP_URL=https://api.evergarden.app
FRONTEND_URL=https://evergarden.app

SANCTUM_STATEFUL_DOMAINS=evergarden.app
SESSION_DOMAIN=.evergarden.app
SESSION_DRIVER=redis

DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=ws.evergarden.app
REVERB_SCHEME=https

FILESYSTEM_DISK=s3
AWS_BUCKET=

VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:correo@evergarden.app

# Configuración postal
POSTAL_MIN_TRANSIT_MINUTES=30
POSTAL_STANDARD_MIN=120
POSTAL_STANDARD_MAX=720
POSTAL_GRACE_PERIOD_MINUTES=15
POSTAL_RANDOM_DAILY_CAP=3
POSTAL_MAX_RECIPIENTS_PER_LETTER=10
POSTAL_MAX_LETTER_CHARS=20000

MODERATION_DRIVER=local        # local | openai | perspective
MODERATION_AUTO_APPROVE_DIRECT=true
```

### 17.5 Comandos artisan propios sugeridos

```
postal:dispatch-due            # ejecución manual del despacho
postal:deliver-arrived
postal:simulate {deliveryId}   # forzar entrega inmediata (solo en local/staging)
postal:stats                   # métricas rápidas por consola
dolls:expire-stale
users:process-deletions
moderation:rescan {model}
evergarden:seed-demo           # datos de demostración coherentes
```

### 17.6 Checklist previo al lanzamiento

- [ ] Verificación de email obligatoria activa
- [ ] Edad mínima verificada en el registro
- [ ] Cartas aleatorias en opt-in explícito, nunca por defecto
- [ ] Filtro de moderación activo en cartas aleatorias y blog
- [ ] Bloqueo y reporte accesibles desde cada carta, post y perfil
- [ ] Recursos de ayuda configurados para los países principales
- [ ] Términos y política de privacidad publicados
- [ ] Backups automáticos con restauración probada
- [ ] Alertas de Horizon y del reloj postal configuradas
- [ ] Tests de policy verdes al 100 %
- [ ] Rate limits activos en todos los endpoints de escritura
- [ ] `APP_DEBUG=false`, `/docs` protegido, Telescope desactivado en producción
- [ ] Lighthouse: PWA instalable y funcional sin conexión

---

## Nota final

Las tres decisiones que más determinarán el éxito técnico de este proyecto:

1. **Separar `letters` de `letter_deliveries`** — te permite todo lo demás: multi-destinatario,
   programaciones, recurrencias y envío aleatorio sin duplicar contenido.
2. **Diseñar la moderación antes que la botella al mar** — el módulo aleatorio es el corazón emocional
   del producto y también su mayor riesgo. Si lo lanzas sin controles, será lo que te obligue a
   apagarlo.
3. **API versionada y con tokens desde el primer día** — el coste hoy es de una tarde; el coste de
   añadirlo cuando ya haya una app móvil publicada es de semanas.
