# Runbook operativo

Qué hacer cuando algo va mal. Escrito para leerse a las tres de la mañana, no para admirarse.

> Guía para **levantar y recorrer** el sistema: `docs/como-probar.md`.
> Este documento es para cuando ya está levantado y algo no funciona.

**Estado de la infraestructura:** hasta cerrar la Fase 5C el entorno es Laragon sin contenedores, con
`queue` y `cache` en `database` y sin Horizon. Los procedimientos marcados _(post-5C)_ no aplican
todavía.

---

## 0. Procesos que deben estar vivos

| Proceso | Comando | Si se cae... |
| --- | --- | --- |
| Scheduler | `php artisan schedule:work` | **Las cartas dejan de despacharse y de entregarse** |
| Worker de colas | `php artisan queue:work --queue=critical,postal,notifications,moderation,default,maintenance` | Los jobs se acumulan sin ejecutarse |
| Reverb | `php artisan reverb:start` | El chat de Dolls cae a polling cada 15 s (ADR-0013). No es urgente |
| Postgres | — | Todo cae |
| Redis | — | La botella al mar deja de encontrar destinatarios (el pool). Nada más, de momento |

**El scheduler y el worker son el reloj postal.** Los dos juntos son el único punto donde un fallo pasa
inadvertido para los usuarios y grave para el producto.

---

## 1. «Las cartas no llegan»

El incidente más importante. Síntoma: entregas `queued` con `scheduled_for` vencido, o `in_transit` con
`delivered_at` pasado, y nadie las mueve.

### Diagnóstico, en orden

```bash
# 1. ¿Hay trabajo atascado?
php artisan postal:stats            # el cuadro completo, sin juzgarlo
php artisan postal:health           # el veredicto: exit 1 si el reloj está parado

# 2. ¿Vive el scheduler? ¿Vive el worker?
ps aux | grep -E "schedule:work|queue:work"

# 3. ¿Se están acumulando jobs?
php artisan queue:monitor postal --max=100
php artisan queue:failed

# 4. ¿Está el despachador bloqueado por su propio candado?
#    DispatchDueLettersJob es ShouldBeUnique con uniqueFor=300.
#    Un proceso muerto a mitad puede dejar el lock puesto hasta 5 min.
```

### Causas por probabilidad

1. **El worker o el scheduler no están corriendo.** Arráncalos. _(post-5C: el supervisor debería
   haberlo hecho solo; si no, revisa sus logs.)_
2. **Entregas reservadas y huérfanas.** `DispatchDueLettersJob` marca un lote con `dispatch_batch_id`
   antes de procesarlo. Si el proceso muere entre la reserva y el despacho, esas filas quedan con
   batch asignado y en `queued` para siempre — el propio job las ignora porque filtra
   `whereNull('dispatch_batch_id')`.

   ```sql
   -- Detectar
   SELECT count(*) FROM letter_deliveries
   WHERE status = 'queued' AND dispatch_batch_id IS NOT NULL
     AND updated_at < now() - interval '30 minutes';

   -- Liberar (solo tras confirmar que ningún worker está trabajando ese lote)
   UPDATE letter_deliveries SET dispatch_batch_id = NULL
   WHERE status = 'queued' AND dispatch_batch_id IS NOT NULL
     AND updated_at < now() - interval '30 minutes';
   ```

   Si esto pasa más de una vez, conviértelo en un job de barrido programado.
3. **Jobs fallando en bucle.** `php artisan queue:failed` y mira la excepción. `DispatchSingleLetterJob`
   tiene `tries=3` y su `failed()` marca la entrega como `failed`; si ves muchas, hay una causa común.
4. **Base de datos o Redis caídos.**

### Después del incidente

Las entregas retrasadas se despachan solas al volver el servicio: el filtro es `scheduled_for <= now()`,
así que recupera sin intervención. **No reinicies estados a mano** salvo el caso 2.

Si el retraso fue de horas, considera avisar a los afectados. La promesa del producto es que la carta
tarde, no que se pierda.

---

## 2. La `APP_KEY`

### Por qué importa más que nada

`letters.body` y `doll_chat_messages.body` están cifrados con ella. **Sin la clave, las cartas son
irrecuperables**, incluso con un backup íntegro de Postgres. No hay servicio de soporte, no hay
recuperación, no hay excusa.

### Dónde debe estar

- En `.env` del servidor (no versionado).
- En el gestor de contraseñas del equipo.
- En una copia offline fuera del proveedor de hosting.

Las tres. Un backup de base de datos que no vaya acompañado de la clave no es un backup.

### Rotarla

Laravel 11+ acepta `APP_PREVIOUS_KEYS`: el descifrado prueba la clave actual y luego las anteriores,
así que la rotación no rompe nada de golpe.

```dotenv
APP_KEY=base64:CLAVE_NUEVA
APP_PREVIOUS_KEYS=base64:CLAVE_ANTIGUA
```

Procedimiento:

1. Backup completo **y verificado** antes de tocar nada.
2. Generar la clave nueva (`php artisan key:generate --show`), no aplicarla todavía.
3. Poner la nueva en `APP_KEY` y la vieja en `APP_PREVIOUS_KEYS`. Desplegar.
4. Comprobar que se leen cartas antiguas (se descifran con la vieja) y que las nuevas se guardan bien.
5. Re-cifrar por lotes: leer y reguardar cada `letters.body` y `doll_chat_messages.body`, en un comando
   dedicado, con transacciones y reanudable.
6. Solo cuando no quede nada con la clave antigua, vaciar `APP_PREVIOUS_KEYS`.

Ojo: las sesiones y los tokens de Sanctum también dependen de la clave. Avisa de que habrá que volver
a iniciar sesión.

---

## 3. Restaurar desde backup

```bash
# 1. Base de datos
pg_restore -d evergarden_restore --clean --if-exists ultimo_backup.dump

# 2. Migraciones pendientes (si el backup es anterior al último despliegue)
php artisan migrate --force

# 3. Verificación que de verdad prueba algo
php artisan tinker
>>> \App\Models\Letter::whereNotNull('body')->first()->body;   # debe descifrarse legible
```

Si el paso 3 devuelve basura o lanza excepción, la `APP_KEY` del entorno no corresponde a esos datos.
**Para y busca la clave correcta antes de hacer nada más.**

Restaurar también los adjuntos del almacenamiento; la base de datos solo guarda rutas.

**Prueba trimestral:** repetir esto entero sobre un entorno limpio. Anotar fecha y cuánto tardó:

| Fecha | Tamaño | Duración | Resultado |
| --- | --- | --- | --- |
| _(pendiente: primera prueba en 5B)_ | | | |

---

## 4. Reporte crítico de moderación

`CriticalAlertDispatcher` envía correo a `MODERATION_ALERT_EMAIL` cuando la categoría es
`minor_safety` o `self_harm` con severidad crítica, además de dejar rastro en el log.

| Categoría | Qué hacer |
| --- | --- |
| `minor_safety` | Prioridad absoluta. Revisar de inmediato en `/admin`. Suspender la cuenta si procede. Conservar las pruebas: **no borrar** el contenido antes de documentarlo. Valorar denuncia a las autoridades del país del usuario |
| `self_harm` | **No se borra contenido.** El contenido se retiene, no se elimina (ADR-0008). Comprobar que a la persona se le mostraron los `support_resources` de su país. Si hay riesgo inminente y hay datos de contacto, valorar avisar a servicios de emergencia locales |
| Resto | Cola normal de `/admin`, por severidad |

**Antes de lanzar hay que decidir por escrito con qué frecuencia se vacía la cola y quién lo hace.**
Está en 5E.

---

## 5. Comprobaciones periódicas

**Diarias** (5 minutos): cola de moderación en `/admin`, `postal:stats`, `queue:failed`, que llegó el
correo del backup.

**Semanales:** reportes de la semana y tendencia, usuarios nuevos vs. activos, espacio en disco,
`failed_jobs` acumulados.

**Trimestrales:** prueba de restauración (§3), revisión de dependencias
(`composer audit`, `npm audit`), repaso de ADRs por si alguna condición de reapertura se cumplió
(ADR-0016 y ADR-0017 dicen explícitamente cuándo volver a mirarlos).

---

## 6. Despliegue y vuelta atrás

```bash
php artisan down --render=maintenance
composer install --no-dev -o
php artisan migrate --force
php artisan config:cache route:cache event:cache
php artisan queue:restart          # post-5C: horizon:terminate
php artisan up
```

**Regla de migraciones:** compatibles hacia atrás siempre. Añadir columna → desplegar → migrar datos →
eliminar columna en un despliegue **posterior**. Con la PWA cacheada y (algún día) una app móvil
instalada, no puedes asumir que todos los clientes se actualizan a la vez.

**Vuelta atrás:** revertir el código es fácil; revertir una migración no. Si una migración destructiva
salió mal, la vía es restaurar backup (§3), no improvisar SQL a las tres de la mañana.

---

## 7. Variables que no pueden faltar en producción

```dotenv
APP_DEBUG=false
APP_KEY=                      # respaldada en tres sitios (§2)
APP_PREVIOUS_KEYS=            # vacío salvo durante una rotación

MODERATION_ALERT_EMAIL=       # un buzón que alguien lee de verdad
OPS_ALERT_EMAIL=              # alertas del reloj postal (5A)

SANCTUM_STATEFUL_DOMAINS=
SESSION_DOMAIN=
FRONTEND_URL=

REVERB_APP_KEY=               # debe coincidir con VITE_REVERB_KEY del front (ADR-0013)
```

---

## 8. Cuándo parar y pedir ayuda

- Sospecha de acceso no autorizado a la base de datos o a la `APP_KEY`.
- Un reporte de `minor_safety` que parece real.
- Pérdida de datos confirmada sin backup válido.
- Requerimiento legal o de autoridad.

En los cuatro casos: documenta qué viste y cuándo **antes** de tocar nada. Lo que se borra intentando
arreglar es lo que luego hace falta.
