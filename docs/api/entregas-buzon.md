# API — Entregas, seguimiento y buzón

Estado: `[ ] contrato definido` · `[ ] backend` · `[ ] front`

> El módulo central del producto. Lee la sección 7.1 del spec (máquina de estados) antes de tocarlo.

## Estados

```
queued → in_transit → delivered → read
   ↓          ↓
cancelled  failed | blocked | cancelled (dentro de la ventana de gracia)
```

- `blocked` se le muestra al **remitente** como `delivered`. Nunca reveles el bloqueo.
- `delivered` es irreversible. El remitente no puede borrar del buzón ajeno.

## Enviar

```
POST /api/v1/letters/{id}/send        # Idempotency-Key recomendado
```

```json
{
  "recipients": [{ "postal_handle": "hana-8c21" }],
  "delivery": {
    "mode": "direct",
    "tier": "standard",
    "arrive_at": "2027-04-12T09:00:00Z",
    "arrive_timezone": "America/New_York"
  },
  "is_anonymous": false,
  "reveal_sender_at": null,
  "allow_read_receipt": true
}
```

- **El usuario elige cuándo debe llegar**, no cuándo sale. El backend calcula `scheduled_for` hacia
  atrás restando el tránsito estimado.
- Si `arrive_at` es null → envío inmediato (sale ya, llega según el tránsito).
- `tier`: `express` (0,5–2 h, recurso limitado a 3/mes) · `standard` (2–12 h) · `slow` (1–3 días).
- Máx. 10 destinatarios. Se crea **una entrega por destinatario**.
- Marca `letter.is_locked = true`.

Respuesta `201`:

```json
{
  "data": [{
    "id": "01J8XK...",
    "status": "queued",
    "scheduled_for": "2027-04-12T02:47:00Z",
    "estimated_delivery_at": "2027-04-12T09:14:00Z",
    "can_cancel": true,
    "recipient": { "display_name": "Hana", "postal_handle": "hana-8c21" }
  }]
}
```

`estimated_delivery_at` lleva ±20 % de ruido respecto a la llegada real. La sorpresa es el producto.

## Mis envíos

```
GET  /api/v1/deliveries              # ?status= cursor
GET  /api/v1/deliveries/{id}
GET  /api/v1/deliveries/{id}/tracking
POST /api/v1/deliveries/{id}/cancel
```

### GET /deliveries/{id}/tracking

Timeline de `delivery_events`. Es la pantalla de "seguimiento postal":

```json
{
  "data": [
    { "event": "queued",           "occurred_at": "...", "label": "Depositada en el buzón" },
    { "event": "dispatched",       "occurred_at": "...", "label": "Recogida por el cartero" },
    { "event": "sorting_office",   "occurred_at": "...", "label": "En la oficina de Leiden",
      "metadata": { "office": "Leiden" } },
    { "event": "out_for_delivery", "occurred_at": "...", "label": "En reparto" },
    { "event": "delivered",        "occurred_at": "...", "label": "Entregada" }
  ]
}
```

Los nombres de oficina son ficticios y del universo del anime. Catálogo en `transit_routes`.

### POST /deliveries/{id}/cancel

- `queued` → siempre permitido.
- `in_transit` → solo dentro de `POSTAL_GRACE_PERIOD_MINUTES` (15) desde `dispatched_at`.
  Después: `409` con `GRACE_PERIOD_EXPIRED`.
- `delivered` → `409` con `INVALID_STATE_TRANSITION`.

## Buzón

```
GET  /api/v1/mailbox                        # ?status=unread|read|archived|favorite  cursor
GET  /api/v1/mailbox/{deliveryId}
POST /api/v1/mailbox/{deliveryId}/open
POST /api/v1/mailbox/{deliveryId}/archive
POST /api/v1/mailbox/{deliveryId}/favorite
POST /api/v1/mailbox/{deliveryId}/reply
GET  /api/v1/mailbox/unread-count
GET  /api/v1/mailbox?updated_since=...       # sincronización delta (móvil)
```

### GET /mailbox — carta cerrada

**Antes de abrir, el contenido no viaja.** Solo el sobre:

```json
{
  "data": [{
    "id": "01J8...",
    "status": "delivered",
    "is_opened": false,
    "envelope": {
      "paper": "parchment", "seal": { "type": "wax", "color": "burgundy", "sigil": "violet" },
      "stamp": "lavender_field"
    },
    "sender": { "display_name": "Violet", "postal_handle": "violet-e4f2", "avatar_url": "..." },
    "is_anonymous": false,
    "title": "Para cuando cumplas veinte",
    "delivered_at": "2027-04-12T09:14:00Z",
    "has_attachments": false
  }]
}
```

Si `is_anonymous = true`, el objeto `sender` es `{ "display_name": "Alguien", "postal_handle": null }`.

### POST /mailbox/{id}/open

Acción explícita. Devuelve la carta completa, fija `read_at`, emite `LetterRead` y notifica al
remitente **solo si** el destinatario tiene `share_read_receipts = true`.

### Reglas críticas

- **Nunca devuelvas entregas `in_transit` al destinatario**, ni en listados ni en conteos. No debe poder
  saber que hay algo en camino.
- `POST /reply` crea un borrador nuevo con `in_reply_to_delivery_id`, no envía nada.
- Cursor pagination obligatoria: el buzón crece indefinidamente.
