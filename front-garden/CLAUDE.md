# front-garden — Vue 3 PWA

Cliente PWA que consume `backend-garden` vía HTTP. **Cero lógica de negocio duplicada**: si necesitas
saber si una carta se puede cancelar, el backend lo dice, el front no lo recalcula.

> Lee también `../CLAUDE.md` (reglas transversales del monorepo).

## Stack

Vue 3 (`<script setup>`) · Vite 5 · TypeScript · Pinia · Vue Router 4 · Axios · Tailwind · Tiptap ·
vite-plugin-pwa · vue-i18n · laravel-echo · Vitest · Playwright

## Estructura

```
src/
├── api/            # un cliente por dominio + client.ts con interceptores
├── assets/         # fonts, textures, seals
├── components/
│   ├── letter/     # LetterEditor, LetterPaper, WaxSeal, EnvelopeOpen
│   ├── mailbox/ doll/ blog/ ui/
├── composables/    # useAuth, useEcho, useCountdown, useOfflineQueue
├── layouts/
├── locales/        # es.json, en.json
├── router/         # index.ts + guards.ts
├── stores/         # auth, letters, mailbox, dolls, notifications, ui
├── types/api.d.ts  # GENERADO desde openapi.json — no editar a mano
└── views/
```

## Reglas

- **Los tipos de la API son generados.** No escribas interfaces a mano para respuestas del backend;
  regenera `types/api.d.ts` desde `../docs/api/openapi.json`.
- **Todo texto visible pasa por i18n.** Nada de strings literales en las plantillas.
- **No hay `localStorage` para datos de cartas.** Borradores offline van a IndexedDB; auth va en cookie
  (PWA) o en almacenamiento seguro (móvil).
- **Nunca pierdas texto escrito.** El editor autoguarda cada 5 s con debounce, y offline escribe a
  IndexedDB antes de intentar la red. Es el fallo más grave posible en este producto.
- **Respeta `prefers-reduced-motion`.** Las animaciones de apertura de sobre son centrales, pero
  opcionales.
- Componentes bajo 200 líneas. Si crece, extrae a composable.
- Accesibilidad AA: contraste, foco visible, navegación por teclado en el editor.

## Documentación interna de este repo

| Archivo | Contenido |
| --- | --- |
| `docs/sistema-diseno.md` | Tokens, tipografía, paleta, componentes de carta |
| `docs/rutas-y-estado.md` | Mapa de rutas, guards, stores de Pinia |
| `docs/pwa-offline.md` | Workbox, IndexedDB, cola offline, push |

El contrato de la API está en `../docs/api/`.

## Definición de "terminado" para una vista

- [ ] Consume el endpoint tal como está en `../docs/api/{modulo}.md`
- [ ] Estados de carga, error y vacío implementados (los tres, siempre)
- [ ] Errores mapeados por `error_code`, no por el texto del mensaje
- [ ] Textos en `locales/es.json` y `en.json`
- [ ] Responsive de 360 px a escritorio
- [ ] Funciona con conexión lenta o intermitente
- [ ] `npm run typecheck` en verde
- [ ] Casilla marcada en `../docs/progreso.md`
