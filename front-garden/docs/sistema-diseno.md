# Sistema de diseño

## Dirección

Escritorio a la luz de una lámpara. Papel, tinta y lacre. Nada rebota, nada parpadea, nada compite por
la atención. El tiempo es parte del producto y la interfaz debe transmitir calma.

## Tipografía

| Uso | Familia |
| --- | --- |
| Cuerpo de la carta | Cormorant Garamond / EB Garamond / Lora (serif) |
| Interfaz | Inter / Source Sans (sans humanista) |
| Detalles manuscritos | Una itálica caligráfica, solo para firmas y sellos |

La tipografía de la carta la elige el remitente (`letters.style.font`) y viene del catálogo del backend.
**No hardcodees la lista en el front.**

## Paleta (tokens Tailwind)

```js
colors: {
  parchment: { 50:'#fdfbf5', 100:'#f7f1e3', 200:'#ebe0c8' },  // papel
  ink:       { 700:'#3b3228', 800:'#2a231b', 900:'#1a1512' },  // texto
  sepia:     { 400:'#a1815c', 500:'#6b4423' },                 // tinta cálida
  wax:       { 500:'#8b2635', 600:'#6d1e2a' },                 // lacre
  violet:    { 400:'#7c6ba8', 500:'#5d4e8c' },                 // acento (Violet)
  sage:      { 400:'#8a9a7b', 500:'#6b7a5c' },                 // secundario
}
```

Modo oscuro: **madera y lámpara**, no gris azulado genérico. Fondos cálidos oscuros
(`#1a1512`), texto crema, acentos igual de saturados.

## Componentes clave

| Componente | Notas |
| --- | --- |
| `LetterPaper` | Renderiza el JSON de Tiptap con el `style` aplicado. Textura por CSS, no imagen pesada |
| `WaxSeal` | SVG con el sigilo. Animación de rotura al abrir |
| `EnvelopeClosed` | El sobre del buzón. **No contiene el texto de la carta** |
| `EnvelopeOpen` | Animación de apertura. El contenido no se muestra hasta terminar |
| `LetterEditor` | Tiptap restringido: negrita, cursiva, subrayado, cita, separador. Nada más |
| `TransitTimeline` | Seguimiento postal con oficinas ficticias |
| `StampPicker` / `PaperPicker` | Alimentados por `GET /letters/styles` |

## Animación

- Duraciones largas: 400–800 ms. Easing suave (`cubic-bezier(.4,0,.2,1)`).
- La apertura del sobre es **el momento** del producto. Merece 1,5 s.
- **`prefers-reduced-motion` obligatorio**: salta directo al contenido.

## Accesibilidad

- Contraste AA mínimo. Ojo con sepia sobre pergamino: verifica cada combinación.
- Tamaño de fuente escalable (`rem`). La carta debe ser legible para todas las edades.
- Foco visible siempre. Navegación por teclado completa en el editor.
- La textura de papel nunca reduce la legibilidad: opacidad baja.
