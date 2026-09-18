# ADR-0013 — Reverb para el canal en tiempo real, al precio de Guzzle 7

**Estado:** aceptado

## Contexto

La Fase 3C necesita el único canal en tiempo real del producto: el chat de Dolls (ADR-0005).
`docs/00-especificacion-tecnica.md` §8.8 y `docs/api/dolls.md` lo escriben directamente contra Reverb
(`Broadcast::channel('doll-request.{requestId}', …)`), y el front ya traía `laravel-echo` y `pusher-js`
en `package.json` desde el andamiaje inicial.

Al instalarlo apareció un conflicto de dependencias que no es obvio y que conviene dejar por escrito:

- `laravel/reverb` sólo existe en la línea **v1** (v1.11.1 es la última), y exige `guzzlehttp/psr7 ^2.6`.
- El proyecto tenía `guzzlehttp/guzzle 8.2` → `guzzlehttp/psr7 3.1`.
- `laravel/framework` acepta `guzzlehttp/guzzle ^7.8.2 || ^8.0`, así que la única resolución posible
  pasa por **bajar Guzzle de 8.2 a 7.15**.

## Decisión

- Se instala `laravel/reverb ^1.11` y se acepta la bajada a `guzzlehttp/guzzle 7.15`
  (`psr7 2.13`, `promises 2.5`).
- El canal se autoriza en `routes/channels.php` y se **vuelve a validar** en
  `DollChatController`/`DollDraftController`. La autorización del canal es una foto del momento de la
  suscripción; una solicitud puede cerrarse entre el handshake y el siguiente POST.
- La ruta `/broadcasting/auth` se registra con `withBroadcasting(..., ['middleware' => ['api',
  'auth:sanctum']])` en vez del `web` por defecto: el guard por defecto sólo serviría a la PWA (cookie
  de sesión) y dejaría fuera al cliente móvil (token Bearer).
- El evento que viaja por el socket (`DollChatMessageSent`) lleva **sólo** `id`, `type`, `sender_id` y
  `created_at`. El cuerpo se relee por la API, que revalida participación. Nada confidencial cruza el
  WebSocket.

## Consecuencias

- Guzzle 7.15 es la línea mayoritaria y sigue mantenida; Laravel 13 la soporta de forma explícita. No
  es una versión abandonada, es la anterior.
- **Si algún día se añade una dependencia que exija `psr7 ^3`, este es el conflicto que aparecerá.**
  Las salidas en ese caso: esperar a un Reverb v2 que soporte psr7 3.x, o mover el broadcasting a
  Pusher/Ably (el código de aplicación no cambiaría: `ShouldBroadcast` y `routes/channels.php` son
  agnósticos del driver, sólo cambia `config/broadcasting.php`).
- El servidor de WebSockets es un proceso más que levantar (`php artisan reverb:start`), igual que el
  worker de colas y el scheduler. En local, Laragon; la contenerización sigue diferida.
- Si `VITE_REVERB_KEY` está vacío, el front **no** se rompe: `src/lib/echo.ts` devuelve `null` y el chat
  cae a refetch cada 15 s. El tiempo real es una mejora, no un requisito para que el módulo funcione.
- Por eso `front-garden/.env` (versionado) deja `VITE_REVERB_KEY` **vacío**: la clave tiene que coincidir
  con la de `backend-garden/.env`, que está en `.gitignore`, así que es un valor por máquina. Cada quien
  la pone en `front-garden/.env.local` (ignorado por `*.local`). Un clon recién hecho arranca en modo
  polling, que funciona, en vez de intentar conectarse con una clave ajena que fallaría en silencio.
- El indicador de escritura va por **whisper** (cliente a cliente): no pasa por el servidor, no se guarda
  y no genera eventos de broadcast. Es azúcar de presencia; el contenido de la conversación sigue
  viajando sólo por la API.
