# API — Cartas (composición)

Estado: `[x] contrato definido` · `[x] backend` · `[ ] front`

> Backend hecho: CRUD, autoguardado, sanitización del cuerpo Tiptap, cifrado de `body`,
> `GET /letters/styles` y `GET /letters/{id}/preview`. Los **adjuntos** (`POST/DELETE
> /letters/{id}/attachments`) llegan con el chunk de envío.

> Una `letter` es **solo contenido y estética**. No tiene destinatario. El destinatario vive en
> `letter_delivery`. Ver ADR-0001.

## Endpoints

```
GET    /api/v1/letters                          # ?status=draft|sent  cursor
POST   /api/v1/letters
GET    /api/v1/letters/{id}
PATCH  /api/v1/letters/{id}                     # 409 LETTER_LOCKED si ya se envió
DELETE /api/v1/letters/{id}                     # solo borradores
POST   /api/v1/letters/{id}/attachments
DELETE /api/v1/letters/{id}/attachments/{attId}
GET    /api/v1/letters/{id}/preview
GET    /api/v1/letters/styles                   # catálogo, cacheable
```

## POST /letters

```json
{
  "title": "Para cuando cumplas veinte",
  "body": { "type": "doc", "content": [ ... ] },
  "style": {
    "paper": "parchment", "texture": "linen", "font": "cormorant", "ink": "sepia",
    "seal": { "type": "wax", "color": "burgundy", "sigil": "violet" },
    "stamp": "lavender_field", "border": "art_nouveau_thin", "flourish": true
  },
  "kind": "direct",
  "in_reply_to_delivery_id": null
}
```

- `body` es **JSON de Tiptap**, no HTML. Cada cliente renderiza como quiera (crítico para la app móvil).
- Se sanitiza en servidor: solo `doc/paragraph/text/blockquote/horizontalRule/hardBreak` y marcas
  `bold/italic/underline`. Todo lo demás se elimina. Nunca confiar en el cliente.
- Máx. 20 000 caracteres (sobre el texto plano). Máx. 50 borradores abiertos → `422 DRAFT_LIMIT_REACHED`.
- `body` se cifra en reposo (`encrypted:array`); `body_plain` guarda el texto para búsqueda/moderación.
- `kind` vía API solo acepta `direct` | `unaddressed`. `random` y `doll_draft` los crean sus flujos.
- `in_reply_to_delivery_id` debe ser una entrega **recibida por el usuario** (si no, `422`).

## Respuesta

```json
{
  "data": {
    "id": "01J8...",
    "title": "...",
    "body": { "type": "doc", "content": [] },
    "style": { },
    "kind": "direct",
    "word_count": 412,
    "reading_time_minutes": 2,
    "is_locked": false,
    "moderation_status": "approved",
    "attachments": [],
    "deliveries_count": 0,
    "created_at": "...", "updated_at": "..."
  }
}
```

## PATCH /letters/{id}

Autoguardado. El front lo llama cada 5 s con debounce. Acepta actualizaciones parciales.
Si `is_locked = true` → `409` con `LETTER_LOCKED`.

## GET /letters/styles

Catálogo servido por el backend, **nunca hardcodeado en el cliente** (permite añadir papeles nuevos sin
desplegar la app móvil):

```json
{
  "data": {
    "papers": [{ "key": "parchment", "name": "Pergamino", "preview_url": "...", "locked": false }],
    "fonts": [{ "key": "cormorant", "name": "Cormorant", "css_family": "'Cormorant Garamond', serif" }],
    "inks": [{ "key": "sepia", "name": "Sepia", "hex": "#6b4423" }],
    "seals": [{ "key": "wax_burgundy", "name": "Lacre burdeos", "asset_url": "..." }],
    "stamps": [ ... ],
    "borders": [ ... ]
  }
}
```

`locked: true` para estilos aún no desbloqueados por el usuario (extensión futura). Dimensiones
servidas: `papers, fonts, inks, seals, sigils, stamps, borders`.

## GET /letters/{id}/preview

Devuelve la carta más el `style` **resuelto** contra el catálogo, para que el cliente pinte la vista
previa a página completa sin una segunda llamada:

```json
{
  "data": {
    "letter": { "id": "01J8...", "body": { }, "style": { } },
    "resolved_style": {
      "paper": { "key": "parchment", "name": "Pergamino", "texture": "linen" },
      "ink":   { "key": "sepia", "name": "Sepia", "hex": "#6b4423" },
      "seal":  { "color": { "key": "wax_burgundy", "name": "Lacre burdeos" }, "sigil": { } }
    }
  }
}
```

## Adjuntos

- Máx. 3 por carta (`422 ATTACHMENT_LIMIT_REACHED`). Imagen ≤ 5 MB (jpg/png/webp).
- `multipart/form-data`, campo `file` + `type` (`image` | `audio` | `pressed_flower`).
- Audio: se acepta `duration_seconds` (entero ≤ 60) del cliente y se guarda en `metadata`.
  El servidor aún no verifica la duración real (necesita ffprobe) — se hace en Fase 4.
- Imagen: el servidor calcula `metadata.width` / `height`.
- **Las cartas aleatorias no admiten adjuntos** (`422 ATTACHMENTS_NOT_ALLOWED`, vector de abuso).
- Solo el autor y solo mientras la carta **no esté enviada** (`409 LETTER_LOCKED`).

## Exportación a PDF

```
GET /api/v1/letters/{letter}/pdf     # la autora exporta su copia
GET /api/v1/mailbox/{delivery}/pdf   # quien la recibió exporta la suya
```

Devuelve `application/pdf` con `Content-Disposition: attachment` y
`Cache-Control: private, no-store` — una carta no se queda en una caché compartida.
`throttle:export-pdf` (10/min): renderizar cuesta órdenes de magnitud más que leer una fila.

Una carta tiene dos dueños, y cada puerta usa el permiso que ya existía:

- `/letters/{id}/pdf` exige ser la autora (`LetterPolicy@view` → 404 sin filtrar existencia).
- `/mailbox/{id}/pdf` exige la misma puerta que abrir el sobre (`viewInMailbox`): **nunca una entrega
  `in_transit`**. La sorpresa es el producto; un PDF no puede ser la puerta trasera.
- El anonimato lo resuelve `SenderView`, el mismo helper que usan los API Resources. Si el buzón aún
  oculta a quien escribe, el PDF tampoco lleva línea de remite.

**Fidelidad.** Se conservan el tono del papel, el color de la tinta y el marco. Las tipografías del
catálogo (Cormorant, EB Garamond, Lora) **no** viajan en el repositorio como ficheros, así que el PDF
cae a la serif que trae Dompdf. Poner los TTF en `storage/fonts` y registrarlos es el camino de mejora.
Ver ADR-0015 para por qué Dompdf y no `spatie/laravel-pdf`.
