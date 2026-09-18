# ADR-0017 — Lo que queda fuera de la Fase 4, y qué haría falta para entrarlo

**Estado:** aceptado

## Contexto

La Fase 4 listaba siete ítems. Cuatro se entregaron (Scramble/OpenAPI, sincronización delta, PDF,
búsqueda). Los tres restantes no se entregan, y cada uno por una razón distinta. Este ADR existe para
que no parezcan olvidos.

## Decisión

### 1. Capacitor + push nativo (FCM/APNs) — **diferido por no poder verificarse**

Se puede escribir la configuración de Capacitor en veinte minutos. Lo que no se puede es **compilar,
arrancar ni probar** el resultado: hace falta Android Studio con su SDK, Xcode en macOS para iOS, y
credenciales reales de FCM y APNs (un `google-services.json`, un certificado de firma, un App ID).

Entregar andamiaje móvil sin ejecutarlo nunca es entregar la *apariencia* de una app móvil. El
siguiente que lo tocara heredaría configuración plausible y no probada, que es peor que una casilla
vacía.

**Lo que ya está hecho y no habrá que rehacer** (spec §12.4 pedía decidirlo "ahora" justamente por esto):
autenticación por token desde el día uno, `error_code` estable, paginación por cursor, sincronización
delta (`?updated_since=`), `push_subscriptions.platform` agnóstico de plataforma y `426 UPGRADE_REQUIRED`
por `X-App-Version`. La PWA es instalable y funciona sin conexión.

**Para entrarlo hace falta:** una máquina con los SDK nativos, una cuenta de Firebase y otra de Apple
Developer, y alguien que pueda ejecutar la app en un dispositivo real.

### 2. Cartas póstumas por inactividad — **diferido por ser una decisión de producto, no técnica**

Es la única funcionalidad del proyecto que **actúa en nombre de alguien que no ha pedido nada en ese
momento**. Si el umbral de inactividad se equivoca, el producto envía la correspondencia íntima de una
persona que está perfectamente viva —de viaje, en el hospital, harta de las pantallas—. El daño no se
deshace: una carta entregada no se puede recoger.

Eso no es un problema de implementación. Las preguntas abiertas son de producto y legales:

- ¿Cuánta inactividad? ¿Cuántos avisos antes, por cuántos canales, y con cuánto margen entre ellos?
- ¿Quién puede cancelarlo: sólo la persona, o también un contacto de confianza designado?
- ¿Qué dice exactamente el **aviso legal** que el checklist exige? Eso lo redacta alguien con criterio
  jurídico, no se improvisa.
- ¿Interactúa con el borrado de cuenta a los 30 días y con `deactivate`?

El propio checklist ya lo marca "(con aviso legal)". Sin ese texto, la funcionalidad no puede lanzarse
aunque el código esté escrito.

**Para entrarlo hace falta:** las respuestas de arriba por escrito, y entonces el diseño natural es un
opt-in explícito, varios recordatorios escalonados, una ventana de gracia larga y cancelación con un
solo clic desde cualquier aviso.

### 3. Particionado de `letter_deliveries` — **no procede todavía, por diseño**

El propio checklist lo condiciona: *"si el volumen lo pide"*. No lo pide. La tabla tiene índices
parciales desde la Fase 1 (ADR-0006) y el volumen actual es de desarrollo.

Particionar antes de necesitarlo añade complejidad permanente en migraciones, claves foráneas y
consultas a cambio de un rendimiento que hoy sobra. Es optimización prematura con nombre propio.

**Para entrarlo hace falta:** una métrica, no una intuición — por ejemplo, que el reloj postal empiece a
degradarse o que los índices dejen de caber cómodamente en memoria.

## Consecuencias

- La Fase 4 se cierra **parcialmente**, y `docs/progreso.md` lo refleja con casillas sin marcar y el
  motivo al lado. Nadie tiene que reconstruir este razonamiento leyendo el historial de git.
- Ninguno de los tres está bloqueado por deuda técnica propia: los tres esperan algo externo
  (herramientas, decisiones, volumen).
- Si alguno se retoma, este documento dice por dónde empezar.
