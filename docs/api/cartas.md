# API — Cartas (composición)

Estado: `[ ] contrato definido` · `[ ] backend` · `[ ] front`

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
- Se sanitiza en servidor. Nunca confiar en el cliente.
- Máx. 20 000 caracteres. Máx. 50 borradores simultáneos.
- `body` se cifra en reposo.

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

`locked: true` para estilos aún no desbloqueados por el usuario (extensión futura).

## Adjuntos

- Máx. 3 por carta. Imagen ≤ 5 MB. Audio ≤ 60 s.
- `multipart/form-data`, campo `file` + `type` (`image` | `audio` | `pressed_flower`).
- **Las cartas aleatorias no admiten adjuntos** (vector de abuso). Ver `botella-al-mar.md`.
