# PWA y estrategia offline

## Manifest

- Iconos 192 y 512, más `maskable`.
- `display: standalone`, `theme_color` de la paleta cálida, `background_color: #fdfbf5`.
- Screenshots para el prompt de instalación.
- `start_url: /escritorio`.

## Estrategias de caché (Workbox)

| Recurso | Estrategia |
| --- | --- |
| App shell, JS, CSS, fuentes | `precache` con revisión por build |
| Texturas, sellos, estampillas | `CacheFirst`, 30 días |
| `GET /mailbox`, `GET /mailbox/{id}` | `StaleWhileRevalidate` + espejo en IndexedDB |
| Borradores | IndexedDB, fuente de verdad hasta sincronizar |
| Resto de `GET` | `NetworkFirst`, timeout 3 s |
| `POST`/`PATCH` sin conexión | `BackgroundSync` queue |

## Cola offline de borradores

**El usuario debe poder escribir una carta completa sin conexión.** Es el requisito más importante de
esta sección.

1. El editor escribe a IndexedDB con un `local_id` **antes** de intentar la red.
2. Al reconectar, se sincroniza y se reconcilia el `local_id` con el `id` del servidor.
3. Conflicto (el servidor tiene una versión más nueva): gana el más reciente por `updated_at`, y se
   conserva el descartado como copia recuperable.

**Nunca pierdas texto escrito.** En un producto donde la gente escribe cartas de despedida a un padre
muerto, perder el borrador es el peor fallo posible. Trátalo como un requisito, no como una mejora.

## Push

1. `GET /api/v1/push/vapid-public-key`.
2. Pedir permiso **solo tras una acción del usuario** (nunca al cargar). El buen momento: después de
   enviar su primera carta.
3. `POST /api/v1/push-subscriptions`.
4. El service worker maneja `push` y `notificationclick` → abre `/buzon/:deliveryId`.

La notificación **nunca revela contenido ni remitente** si la carta es anónima.

## Estados obligatorios en cada vista

Carga, error, vacío y **sin conexión**. Los cuatro, siempre. El estado offline debe indicar qué
funciona igual (leer el buzón cacheado, escribir borradores) y qué no.

## Objetivos Lighthouse

- PWA instalable ✔
- Rendimiento móvil > 90
- Accesibilidad > 95
- Funciona con el modo avión activado tras la primera visita
