# ADR-0015 — Dompdf en vez de un navegador headless para exportar cartas

**Estado:** aceptado

## Contexto

`docs/00-especificacion-tecnica.md` §8 propone exportar cartas a PDF "muy solicitado y fácil con
`spatie/laravel-pdf`". Ese paquete es una envoltura sobre **Browsershot**, que a su vez lanza un
**Chromium headless** vía Node.

Eso significa que cualquier máquina que renderice un PDF necesita Node y un navegador completo
instalados y actualizados: el entorno local (Laragon), CI, y cada worker de la cola. La contenerización
sigue diferida (ver `docs/progreso.md`), así que hoy ese coste recae sobre personas, no sobre una imagen.

Lo que hay que renderizar es, además, modesto: una hoja A4 con un color de papel, un color de tinta, un
título, párrafos, citas, una regla y un marco opcional. No hay maquetación compleja, ni flexbox, ni
tipografía variable, ni SVG.

## Decisión

Se usa **`dompdf/dompdf`** (PHP puro, MIT) envuelto en `App\Services\Postal\LetterPdfRenderer`.

- Sin Node, sin navegador, sin binarios: `composer install` y funciona. En CI también.
- El renderizador se configura con `isRemoteEnabled=false` e `isJavascriptEnabled=false`: el cuerpo de
  una carta lo escribe una persona, y no debe poder provocar peticiones de red desde el servidor.
- `TiptapContent::toHtml()` escapa todo al salir. El cuerpo ya se saneó al entrar, pero un cuerpo
  guardado antes de un cambio de lista blanca sería si no un XSS almacenado hacia el renderizador.

## Consecuencias

- **Las tipografías del catálogo no se conservan.** Cormorant, EB Garamond y Lora son webfonts de Google
  y no hay ficheros en el repositorio, así que el PDF cae a la serif que Dompdf trae de serie. Papel,
  tinta y marco sí viajan. Está anotado en `docs/api/cartas.md`, no escondido.
  **Mejora directa:** poner los TTF en `storage/fonts` y registrarlos en el renderizador; no requiere
  cambiar la decisión.
- CSS limitado: nada de grid, flex o `calc()`. Para el diseño de una carta es suficiente, y si algún día
  deja de serlo, el punto de cambio es una clase (`LetterPdfRenderer`), no el dominio.
- Se renderiza **síncrono**, dentro de la petición. Una carta son ~2 KB de PDF y milisegundos. Si
  apareciera la exportación masiva ("todas mis cartas en un zip"), ahí sí toca cola — y entonces
  conviene reevaluar el navegador headless, que es donde de verdad rinde.
- Si en el futuro se quiere fidelidad exacta con la vista web, la salida es sustituir el renderizador por
  Browsershot manteniendo la interfaz. Nada del dominio depende de Dompdf.
