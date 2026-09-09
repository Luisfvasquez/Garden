# API — Botella al mar (cartas aleatorias)

Estado: `[x] contrato definido` · `[x] backend` · `[~] front`

> **Módulo de mayor riesgo del proyecto.** Enviar texto libre a un desconocido es un vector directo de
> acoso y spam. Los controles de este documento no son opcionales. Ver ADR-0004 y ADR-0010.

> **Backend (Fase 2C):** todo tras el flag `bottle_at_sea` (`feature:bottle_at_sea` → 404).
> `RandomLetterSender` (gates en orden: `verified` → antigüedad ≥7 d → sin `restrict_random` →
> cuota 3/día·10/semana → sin adjuntos → **moderación síncrona** → pool no vacío), `RandomRecipientPool`
> (interfaz; Redis/`predis` en prod, array en tests — ADR-0010), `RandomRecipientPicker`
> (`SRANDMEMBER` + verificación en BD), `RefreshRandomRecipientPoolJob` (cada 15 min), integración en
> `DispatchSingleLetterJob` (destinatario al despachar; sin pool → `failed`/`no_recipient` + vuelve a
> borradores). `moderation_actions` + `RandomAbuseGuard` (2 reportes `actioned` → `restrict_random`).
> Un bloqueo cancela las entregas aleatorias `queued`/`held` del par.
>
> **Desviaciones respecto a este documento (actualizadas aquí):**
> - Filtro **síncrono** en la petición (driver local, determinista): `rejected` → `422 CONTENT_FLAGGED`;
>   `flagged` (típicamente autolesión) → **no se bloquea**: `202`, entrega en estado **`held`**
>   (revisión humana antes de llegar a un desconocido), `data.held_for_review: true`. `approved` → `201`.
> - Nuevo `error_code` `RANDOM_RESTRICTED` (`403`) cuando hay un `restrict_random` vigente.
> - `POST /mailbox/{id}/reply-anonymous` acepta `{ body: <doc tiptap>, tier? }` y **envía** la única
>   respuesta (no crea borrador). Segundo intento → `409 CHANNEL_CLOSED`.
> - `POST /mailbox/{id}/open-correspondence` lo llama **cada parte una vez**; cuando ambas → se revelan
>   los handles (en `DeliveryResource.recipient` y en el buzón). Respuesta:
>   `{ data: { sender_accepted, recipient_accepted, opened } }`.
> - `message_to_stranger` se valida pero **no se persiste** todavía.
> - Reciprocidad (leer la última recibida antes de enviar): no implementada (era opcional).

## Endpoints

```
POST /api/v1/letters/{id}/send-random     # Idempotency-Key recomendado
GET  /api/v1/random/quota                 # cuota restante del usuario
POST /api/v1/mailbox/{deliveryId}/reply-anonymous
POST /api/v1/mailbox/{deliveryId}/open-correspondence   # proponer pasar a correspondencia abierta
```

## POST /letters/{id}/send-random

```json
{ "tier": "standard", "message_to_stranger": null }
```

Respuesta `201`:

```json
{
  "data": {
    "id": "01J8...",
    "status": "queued",
    "estimated_delivery_at": "...",
    "recipient": null,
    "quota_remaining_today": 2
  }
}
```

**El destinatario se resuelve en el momento del despacho, no al enviar.** Y nunca se revela al
remitente: `recipient` es siempre `null` en este flujo.

## Requisitos para emitir

| Requisito | Valor |
| --- | --- |
| Email verificado | obligatorio |
| Antigüedad de la cuenta | ≥ 7 días |
| Cuota | 3/día, 10/semana |
| Moderación | **siempre** pasa por filtro antes de encolar |
| Adjuntos | **no permitidos** |
| Estado de la cuenta | `active`, sin `restrict_random` vigente |

Fallos: `422 QUOTA_EXCEEDED`, `422 EMAIL_NOT_VERIFIED`, `422 CONTENT_FLAGGED`,
`422 ACCOUNT_TOO_NEW`, `409 NO_RANDOM_RECIPIENT`.

## Requisitos del receptor

Consulta que aplica el `RandomRecipientPicker`:

- `status = active` y email verificado.
- `accepts_random_letters = true` — **opt-in explícito, jamás por defecto**.
- `last_active_at` en los últimos 30 días.
- No ha bloqueado al remitente (ni al revés).
- No ha superado su `random_letters_daily_cap` (default 3).
- No ha recibido carta de este mismo remitente en 90 días.

## Selección eficiente

`inRandomOrder()` hace full scan y no escala. Implementación correcta:

- `RefreshRandomRecipientPoolJob` cada 15 min mantiene un `SET` de Redis con los IDs elegibles.
- La selección es `SRANDMEMBER` + verificación puntual contra la base de datos (las condiciones pueden
  haber cambiado en esos 15 min).
- Si el pool está vacío tras N intentos → entrega `failed` con `no_recipient`, se notifica al remitente
  y la carta vuelve a borradores.

## Anonimato y respuesta

- Por defecto el remitente aparece como seudónimo, nunca como perfil real.
- El receptor puede responder **una sola vez** por el mismo canal anónimo
  (`POST /mailbox/{id}/reply-anonymous`).
- Para pasar a correspondencia abierta, **ambos deben aceptar**
  (`POST /mailbox/{id}/open-correspondence` por las dos partes). Solo entonces se revelan los handles.

## Consecuencias automáticas

- 2 reportes confirmados sobre cartas aleatorias → `restrict_random` automático (`moderation_actions`).
- Un bloqueo cancela cualquier entrega aleatoria pendiente entre ese par.
- Si el filtro detecta ideación autolesiva en una carta aleatoria entrante, se **retiene para revisión
  humana** antes de entregarla a un desconocido.

## Idea de producto (opcional, fase 2)

Reciprocidad: para enviar una botella al mar debes haber leído la última que recibiste. Sube la calidad
del ecosistema y reduce el spam sin controles adicionales.
