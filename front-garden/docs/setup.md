# Setup del proyecto (front-garden)

Guía de inicialización. Ejecutar **una sola vez**, al arrancar el repo.
Si ya existe `package.json`, este documento sirve solo como referencia de por qué está cada cosa.

---

## 1. Andamiaje

Desde `garden/`:

```bash
npm create vue@latest front-garden
```

| Pregunta | Respuesta |
| --- | --- |
| TypeScript | **Sí** |
| JSX | No |
| Vue Router | **Sí** |
| Pinia | **Sí** |
| Vitest | **Sí** |
| End-to-End Testing | **Playwright** |
| ESLint | **Sí** |
| Prettier | **Sí** |

La carpeta `front-garden/` ya existe con `CLAUDE.md` y `docs/`. Si el comando se queja de que no está
vacía:

```bash
npm create vue@latest front-garden-tmp
rsync -a front-garden-tmp/ front-garden/
rm -rf front-garden-tmp front-garden/.gitkeep
cd front-garden && npm install
```

---

## 2. Dependencias

```bash
# Runtime
npm i axios @tanstack/vue-query vue-i18n @vueuse/core dexie
npm i @tiptap/vue-3 @tiptap/pm @tiptap/starter-kit
npm i laravel-echo pusher-js

# Dev
npm i -D tailwindcss @tailwindcss/vite
npm i -D vite-plugin-pwa
npm i -D openapi-typescript
npm i -D @types/node
```

**Por qué cada una:**

| Paquete | Motivo |
| --- | --- |
| `dexie` | IndexedDB para la cola offline de borradores. La API nativa es hostil. Necesario desde fase 1 |
| `@tanstack/vue-query` | Caché, revalidación y estados de carga/error automáticos. Deja a Pinia solo para sesión y UI |
| `@tiptap/*` | Editor de cartas. Extensiones **restringidas** (ver `sistema-diseno.md`) |
| `laravel-echo` + `pusher-js` | Cliente de Reverb. Solo se usa en el chat de Dolls y notificaciones |
| `openapi-typescript` | Genera `types/api.d.ts` desde el contrato del backend |

> Tailwind v4 se instala como plugin de Vite y se configura desde CSS, sin `tailwind.config.js`.
> Si se prefiere v3, la instalación cambia (`npx tailwindcss init -p`).

---

## 3. `vite.config.ts`

```ts
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'prompt',
      manifest: {
        name: 'Evergarden',
        short_name: 'Evergarden',
        start_url: '/escritorio',
        display: 'standalone',
        theme_color: '#5d4e8c',
        background_color: '#fdfbf5',
        icons: [
          { src: '/icons/192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icons/maskable.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        navigateFallbackDenylist: [/^\/api/, /^\/sanctum/],
      },
      devOptions: { enabled: false },
    }),
  ],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    host: 'evergarden.test',
    port: 5173,
    proxy: {
      '/api':     { target: 'http://localhost:8000', changeOrigin: false },
      '/sanctum': { target: 'http://localhost:8000', changeOrigin: false },
    },
  },
})
```

### El proxy no es opcional

Las cookies de Sanctum solo funcionan si el navegador cree que API y front comparten origen. Con el
proxy, el front pide a `/api/...` en su propio origen y Vite lo reenvía al backend. **`changeOrigin`
debe ser `false`** para que la cookie se conserve.

Sin esto: cookies que no se guardan, `419 CSRF token mismatch` y una tarde perdida.

`navigateFallbackDenylist` evita que el service worker intercepte `/api` y `/sanctum` y devuelva el
`index.html` en lugar de la respuesta JSON. Es un fallo silencioso y difícil de diagnosticar.

---

## 4. Hosts y entorno

`/etc/hosts` (o `C:\Windows\System32\drivers\etc\hosts`):

```
127.0.0.1  evergarden.test api.evergarden.test
```

`.env` del front:

```dotenv
VITE_API_URL=/api/v1
VITE_APP_VERSION=0.1.0
VITE_REVERB_KEY=
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

**`VITE_API_URL` relativo, nunca absoluto.** Es lo que hace que el proxy funcione en local y que en
producción apunte al dominio correcto sin tocar código.

`.env` del backend (parte relevante):

```dotenv
SANCTUM_STATEFUL_DOMAINS=evergarden.test:5173
SESSION_DOMAIN=.evergarden.test
FRONTEND_URL=http://evergarden.test:5173
```

---

## 5. Estructura de carpetas

```bash
cd front-garden/src
mkdir -p api composables layouts locales stores types \
  components/{letter,mailbox,doll,blog,ui} \
  assets/{fonts,textures,seals}
```

Coincide con lo declarado en `../CLAUDE.md`.

---

## 6. Scripts (`package.json`)

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vue-tsc --build && vite build",
    "preview": "vite preview",
    "test": "vitest",
    "test:e2e": "playwright test",
    "typecheck": "vue-tsc --noEmit",
    "lint": "eslint . --fix",
    "api:types": "openapi-typescript ../docs/api/openapi.json -o src/types/api.d.ts"
  }
}
```

`api:types` fallará hasta que el backend genere `openapi.json` con Scramble. Déjalo puesto igual: es el
recordatorio de que **los tipos de la API son generados, no escritos a mano**.

---

## 7. Orden de trabajo recomendado

**No construyas vistas todavía.** Con el andamiaje montado, el orden que menos tiempo desperdicia es:

1. **`src/api/client.ts`** — instancia de Axios con los interceptores de `rutas-y-estado.md`
   (`X-App-Version`, CSRF, 401, 419, 429, 426, mapeo por `error_code`).
2. **`src/assets/main.css`** — tokens del sistema de diseño: paleta, tipografías, escalas.
   Ver `sistema-diseno.md`.
3. **`src/router/index.ts` + `guards.ts`** — rutas y guards, aunque las vistas sean placeholders.
4. **`src/stores/auth.ts`** — sesión y usuario actual.
5. **Primera vista real: login.** Y solo cuando el backend tenga auth funcionando.

Con las piezas 1 y 2 hechas, cada vista posterior sale en una fracción del tiempo. Empezar por las
vistas y arreglar los cimientos después significa reescribirlas todas.

---

## 8. Verificación

```bash
npm run dev        # arranca en http://evergarden.test:5173
npm run typecheck  # sin errores
npm run build      # genera dist/ y el service worker
npm run preview    # comprobar que la PWA es instalable
```

Comprobaciones manuales al terminar el setup:

- [ ] `curl` a través del proxy: `GET /api/v1/health` responde desde el backend
- [ ] La cookie de sesión se guarda tras `GET /sanctum/csrf-cookie`
- [ ] `npm run build` no lanza avisos del manifest
- [ ] Lighthouse detecta la app como instalable
