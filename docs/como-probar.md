# Cómo levantar y probar Evergarden en local

Guía de arranque del MVP. Si algo no cuadra con la realidad, gana la realidad: corrígelo aquí.

## 0. Qué necesitas encendido

| Servicio | Para qué | Obligatorio |
| --- | --- | --- |
| PostgreSQL 17 | todo | **sí** |
| Redis | pool de "botella al mar" | no (hay respaldo en array) |
| Node 22+ / PHP 8.4 | front / backend | **sí** |

## 1. Primer arranque

```bash
# Backend
cd backend-garden
composer install
php artisan migrate            # crea el esquema
php artisan db:seed            # rutas postales + recursos de ayuda
php artisan evergarden:seed-demo   # escenario de prueba (ver §3)

# Front
cd ../front-garden
npm install
```

## 2. Los procesos

Cada uno en su terminal. **Los dos primeros son imprescindibles; sin el tercero las cartas no
llegan nunca.**

```bash
# 1) API                    → http://localhost:8000
cd backend-garden && php artisan serve

# 2) Front                  → http://evergarden.test:5173 (o http://localhost:5173)
cd front-garden && npm run dev

# 3) EL RELOJ POSTAL — sin esto las cartas se quedan en tránsito para siempre
cd backend-garden && php artisan schedule:work

# 4) Cola (notificaciones, push, moderación)
cd backend-garden && php artisan queue:work

# 5) WebSockets — sólo si quieres probar el chat de Dolls en tiempo real
cd backend-garden && php artisan reverb:start
```

**Sobre el punto 5:** `front-garden/.env` deja `VITE_REVERB_KEY` vacío a propósito (debe coincidir con
`backend-garden/.env`, que está en `.gitignore`). Sin clave **el chat sigue funcionando**, sólo que
refresca cada 15 s en vez de en tiempo real. Para activarlo, copia la clave a
`front-garden/.env.local` y reinicia Vite. Ver ADR-0013.

## 3. Datos de prueba

`php artisan evergarden:seed-demo` deja un escenario completo e **idempotente** (puedes repetirlo; borra
la tanda anterior). Todas las cuentas usan la contraseña `password`.

| Correo | Quién es | Para probar |
| --- | --- | --- |
| `violet@demo.evergarden.test` | remitente principal | escribir, enviar, seguimiento |
| `gilbert@demo.evergarden.test` | destinatario | buzón, abrir sobre, responder |
| `cattleya@demo.evergarden.test` | Doll verificada | panel de Doll, chat, borradores |
| `iris@demo.evergarden.test` | Doll **pendiente** de revisión | cola de verificación en `/admin` |
| `claudia@demo.evergarden.test` | **admin** (Claudia Hodgins) | panel de moderación en `/admin` |
| `erica@demo.evergarden.test` | Doll verificada, **no disponible** | que el directorio filtre bien |

Incluye cartas en los seis estados, una aleatoria retenida (para que la cola de moderación no esté
vacía), una programación anual con ocurrencias, tres posts de blog (uno esperando consentimiento) y un
chat de Dolls con un borrador v1 **sin aprobar**.

## 4. Recorrido sugerido

1. **El correo (el producto).** Entra como `violet`, escribe una carta, elige estética, envía a
   `gilbert`. Mira el seguimiento: está en tránsito. Con `schedule:work` corriendo, llega sola.
   Entra como `gilbert` → el sobre está cerrado; ábrelo (se rompe el lacre) y responde.
   - Para no esperar: `php artisan tinker` → `App\Models\LetterDelivery::latest()->first()->update(['delivered_at' => now()])`.
2. **Descargar en PDF.** Al final de una carta abierta, "Descargar PDF".
3. **Dolls.** Como `violet`, pide ayuda a `cattleya`. Como `cattleya`, entra en **Panel de la Doll**,
   acepta, empieza, y usa el chat. Comparte un borrador; apruébalo como `violet` → se crea una carta
   tuya, lista para enviar.
4. **Filtro anti-contactos.** En el chat, escribe un correo electrónico o un teléfono: el mensaje
   **sale igual** y aparece un aviso del sistema para ambas partes. Es deliberado (ADR-0005).
5. **Moderación.** Entra en `http://localhost:8000/admin` como `claudia`. Verás la cola de reportes, el
   blog retenido y las Dolls pendientes de verificar.
6. **Reportar y bloquear.** Disponible al pie de cada carta, post, comentario, mensaje de chat y perfil.
7. **Buscar.** En el blog, busca "cartas": encuentra también "carta" (lematiza en español).

## 5. Cosas que confunden si no las sabes

- **El correo sale al log.** `MAIL_MAILER=log`, así que los enlaces de verificación y de recuperación
  están en `backend-garden/storage/logs/laravel.log`. Búscalos con `grep -n "verify" storage/logs/laravel.log | tail -1`.
  Los usuarios de demo ya vienen verificados.
- **Una carta en tránsito no aparece en el buzón, ni en los contadores.** No es un fallo: la sorpresa es
  el producto.
- **El bloqueo es silencioso** (ADR-0007). Al bloquear, el remitente sigue viendo "entregada".
- **La media de una Doll tarda hasta una hora** en moverse tras valorar: la recalcula un job por hora,
  entera y desde cero. Para verlo ya: `php artisan tinker` → `(new App\Jobs\Dolls\RecalculateDollRatingsJob)->handle()`.
- **Con menos de 3 valoraciones no se muestra media**, sino "sin valoraciones suficientes".
- **`/docs/api`** sirve la documentación interactiva de la API. Abierta en `local`; fuera de `local`
  sólo para staff.

## 6. Comprobar que todo está sano

```bash
cd backend-garden && ./vendor/bin/pest && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse
cd ../front-garden && npm run test && npm run typecheck && npm run build-only
```

Si tocas un API Resource, regenera el contrato o el `typecheck` del front fallará a propósito
(ADR-0014):

```bash
cd backend-garden && php artisan scramble:export --path=../docs/api/openapi.json
cd ../front-garden && npm run api:types
```
