# API — Auto Memory Dolls

Estado: `[x] contrato definido` · `[x] backend (3A–3D)` · `[x] front (3A–3D)`

> **Única excepción** a la regla "todo se comunica por cartas". Contratar a alguien para que te ayude a
> escribir. Ver ADR-0005.

> **Backend (Fase 3A — `doll_profiles`):** `doll_profiles` (capacidad, no rol — `users.role` solo pasa a
> `doll` cuando `verified_at` se marca a mano, vía `DollProfile::markVerified()`/`markUnverified()`),
> tras el flag `dolls`. `GET /dolls` (directorio, solo perfiles verificados, filtros
> `specialty`/`language`/`available`, `whereJsonContains`), `GET /dolls/{handle}`,
> `POST/PATCH /me/doll-profile`, `POST /me/doll-profile/availability`. Verificación manual desde
> Filament (`DollProfileResource`, cola de pendientes con badge de nav).
>
> **Backend (Fase 3B — `doll_requests`):** máquina de estados vía métodos guardados en el modelo
> (`accept()`/`reject()`/`start()`/`cancel()`/…, nunca `->update(['status' => …])`), mismo patrón que
> `LetterDelivery`. `DollRequestPolicy` (dos perspectivas — cliente y Doll — 404 para cualquier otra
> persona, como `LetterDeliveryPolicy`). `ExpireStaleDollRequestsJob` corre cada hora.
>
> **Backend (Fase 3C — chat, borradores y aprobación):** `doll_chat_messages` (`body` cifrado con el
> cast `encrypted`, igual que `letters.body`), Reverb + `routes/channels.php` con **doble validación**
> (canal + controlador), `ContactExchangeGuard` (avisa, no bloquea) y `DraftApprover` (aprobar = crear
> la carta del cliente + cerrar la solicitud, en una transacción). Ver ADR-0013 para el porqué de
> Reverb v1 y Guzzle 7.
>
> **Backend (Fase 3D — valoraciones y retención):** `POST /doll-requests/{id}/rate`,
> `RecalculateDollRatingsJob` (horario, recalcula el agregado **desde cero**) y `PurgeOldDollChatsJob`
> (diario 04:00, borra transcripciones 90 días después de cerrar). `config/dolls.php` centraliza la
> retención y el mínimo de valoraciones para mostrar media.
>
> **Desviaciones respecto a este documento (actualizadas aquí):**
> - `GET /dolls/{handle}` usa `postal_handle`, no el `id` literal — mismo identificador público que
>   `GET /users/{postal_handle}` en todo el resto del contrato.
> - `POST /doll-requests` acepta `doll_handle` (el `postal_handle` público), no `doll_id`: el cliente
>   nunca tiene el uuid crudo de la Doll. El servidor lo resuelve.
> - Añadido `GET /me/doll-profile` (no estaba en el contrato) para que el front sepa si el usuario ya
>   tiene perfil y en qué estado (`verified_at`).
> - Sin pagos: ver ADR-0012. `rate_type` distinto de `free` es informativo.
> - `doll_profiles.verified_at` y `max_concurrent_requests` solo se exponen al dueño del perfil, nunca
>   a un tercero (ni siquiera si el perfil es visible en el directorio).
> - `max_concurrent_requests` cuenta solicitudes `accepted`/`in_progress`/`awaiting_client` — **no**
>   `pending`. Una ráfaga de solicitudes entrantes no debe por sí misma bloquear a la Doll de recibir
>   más; lo que ocupa un "cupo" es el trabajo activo, no las preguntas sin responder.
> - `POST /doll-requests/{id}/complete` **no existe como endpoint independiente en 3B.** La transición
>   real a `completed` la dispara `POST /drafts/{draftId}/approve` (Fase 3C, ver "Aprobar y cerrar" más
>   abajo) — el modelo ya tiene el método guardado (`DollRequest::complete()`), listo para que 3C lo
>   llame. Igual con la alternancia `in_progress ⇄ awaiting_client`: los métodos (`awaitClient()`/
>   `resume()`) existen pero solo el chat de 3C los dispara.
> - `POST /doll-requests/{id}/rate` se difiere a cuando exista `RecalculateDollRatingsJob`: valorar una
>   solicitud que aún no se puede completar (ver punto anterior) no tenía sentido enviarlo en 3B. El
>   modelo ya tiene `DollRequest::rate()` guardado y listo.
> - **La transcripción se puede leer después de cerrar la solicitud.** `GET .../messages` no exige canal
>   abierto; sólo la escritura lo exige. El histórico es consultable 90 días (spec §8.8 paso 9), y
>   negarle a alguien la conversación que acaba de tener sería una pérdida de datos, no una protección.
> - `POST /drafts/{draftId}/approve` cuelga de la solicitud —
>   `POST /doll-requests/{id}/drafts/{draftId}/approve` — con `scopeBindings()`: un borrador de otra
>   solicitud da 404 sin revelar que existe.
> - **`in_progress ⇄ awaiting_client` es automático.** No hay endpoint para alternarlos: escribir como
>   Doll aparca la solicitud en el cliente (`awaiting_client`), y que el cliente conteste la devuelve a
>   `in_progress`. Es información de "de quién es el turno", y pedírsela a mano a la gente sería ruido.
> - `draft_version` lo asigna **el servidor** (`DraftApprover::nextVersion()`), no el cliente: dos
>   borradores no pueden reclamar ser la v3. El contrato lo mostraba dentro de `draft_payload`; en la
>   práctica es una columna propia, con índice único `(doll_request_id, draft_version)`.
> - `POST /reports` sobre un `doll_chat_message` exige **ser parte de la solicitud**; si no, 404. Es el
>   único sitio que acepta un id de chat desde fuera.
> - `payment_status` no se implementa: ADR-0012 (sin pagos).
> - **`rating_avg` sale `null` mientras `rating_count < dolls.min_ratings_to_display` (3 por defecto).**
>   Una sola reseña de 5 estrellas no es una valoración, es una anécdota, y publicarla como "5,0"
>   engaña al siguiente cliente. `rating_count` sí se expone siempre.
> - **El endpoint de valorar no toca el agregado del perfil.** `doll_profiles.rating_avg`/`rating_count`/
>   `completed_requests_count` son valores derivados: los recalcula `RecalculateDollRatingsJob` cada
>   hora, entero, desde `doll_requests`. Una media mantenida a incrementos se desvía en cuanto algo se
>   borra o se corrige. Consecuencia visible: tras valorar, la media pública tarda hasta una hora.
> - `PurgeOldDollChatsJob` borra **sólo la transcripción**. La solicitud sobrevive (auditoría: quién,
>   cuándo, qué valoración) y la carta también — es del cliente. Una conversación abierta no se toca
>   nunca, por vieja que sea.

## Endpoints

```
GET    /api/v1/dolls                          # directorio. ?specialty=&language=&available=
GET    /api/v1/dolls/{id}

POST   /api/v1/doll-requests
GET    /api/v1/doll-requests                  # ?role=client|doll  ?status=
GET    /api/v1/doll-requests/{id}
POST   /api/v1/doll-requests/{id}/accept
POST   /api/v1/doll-requests/{id}/reject
POST   /api/v1/doll-requests/{id}/start
POST   /api/v1/doll-requests/{id}/complete       # NO existe: lo dispara .../drafts/{id}/approve
POST   /api/v1/doll-requests/{id}/cancel
POST   /api/v1/doll-requests/{id}/rate           # solo el cliente, una vez, sobre completed

GET    /api/v1/doll-requests/{id}/messages    # cursor, orden ascendente
POST   /api/v1/doll-requests/{id}/messages
POST   /api/v1/doll-requests/{id}/drafts          # solo la Doll
POST   /api/v1/doll-requests/{id}/drafts/{draftId}/approve   # solo el cliente; cierra la solicitud

POST   /api/v1/me/doll-profile                # solicitar el rol
PATCH  /api/v1/me/doll-profile
POST   /api/v1/me/doll-profile/availability
```

## Estados

```
pending ──accept──► accepted ──start──► in_progress ⇄ awaiting_client
   │                                          │
   │ reject                                   │ complete
   ▼                                          ▼
rejected                                  completed
   │
   │ sin respuesta en 48 h
   ▼
expired
```

## POST /doll-requests

```json
{
  "doll_handle": "cattleya",
  "occasion": "Disculpa a un hermano",
  "brief_notes": "Llevamos tres años sin hablar...",
  "target_recipient_hint": "Mi hermano mayor",
  "desired_tone": ["íntimo", "sobrio"],
  "deadline_at": "2026-09-15T00:00:00Z"
}
```

- `target_recipient_hint` es texto libre **sin datos personales de terceros**. Filtra PII.
- `expires_at` se fija a +48 h. `ExpireStaleDollRequestsJob` la mueve a `expired`.
- La Doll debe tener `is_available = true` y no superar `max_concurrent_requests`.
  Si no: `409 DOLL_UNAVAILABLE`.

## Chat

**El canal solo existe mientras `status IN ('in_progress','awaiting_client')`.**
Fuera de eso: `403 CHANNEL_CLOSED`. Se valida en dos sitios:

```php
// routes/channels.php
Broadcast::channel('doll-request.{requestId}', function (User $user, string $requestId) {
    $r = DollRequest::find($requestId);
    return $r
        && in_array($r->status, ['in_progress', 'awaiting_client'], true)
        && in_array($user->id, [$r->client_id, $r->doll_id], true);
});
```

Y **de nuevo** en el controlador de mensajes. Nunca confíes solo en la autorización del canal.

### GET /doll-requests/{id}/messages

Cursor, orden **ascendente** (una conversación se lee hacia adelante, al revés que el buzón).
Legible aunque el canal esté cerrado; sólo escribir exige canal abierto.

```json
{
  "data": [{
    "id": "uuid",
    "doll_request_id": "uuid",
    "type": "text",
    "body": "¿Cómo era él cuando eran niños?",
    "sender": { "postal_handle": "cattleya-4f21", "display_name": "Cattleya", "avatar_url": null },
    "is_mine": false,
    "draft_payload": null,
    "draft_version": null,
    "draft_approved_at": null,
    "pii_flags": [],
    "read_at": null,
    "created_at": "2026-09-17T10:12:00Z"
  }],
  "meta": { "per_page": 30, "next_cursor": null, "has_more": false }
}
```

`type`: `text` | `draft` | `system` | `attachment`. Un `system` no tiene `sender` — lo escribe la
plataforma, no una persona.

### POST /doll-requests/{id}/messages

```json
{ "type": "text", "body": "¿Cómo era él cuando eran niños?" }
```

`body` máx. 4000 caracteres. Se guarda **cifrado** (`encrypted`), igual que `letters.body`.

**Filtro anti-intercambio de contactos.** Si `PiiScanner` detecta email, teléfono, @handle o URL:

- el mensaje **se envía igual** — el filtro avisa, no bloquea (al revés que en las cartas aleatorias,
  donde el mismo patrón se rechaza);
- se devuelve con `pii_flags: ["email", ...]`;
- se añade a la conversación un mensaje `system` que ambas partes ven;
- se registra una `moderation_actions` de tipo `warn`, origen `filter`, razón
  `contact_exchange_in_doll_chat`.

El motivo de avisar y no bloquear está en `backend-garden/docs/moderacion.md` §PII: un brief legítimo
contiene cosas que parecen PII ("el del 91 de la calle Mayor"), y bloquear en silencio sólo enseña a
ofuscar.

### POST /doll-requests/{id}/drafts

La Doll comparte un borrador versionado. **Sólo la Doll**; el cliente recibe 404.

```json
{
  "draft_payload": { "title": "Para mi hermano", "body": { "type": "doc", "content": [] } },
  "note": "Primera versión, dime qué cambiarías."
}
```

`version` **no se envía**: lo asigna el servidor y vuelve como `draft_version` (columna propia, no
dentro de `draft_payload`). `body` se sanitiza con la misma lista blanca de Tiptap que una carta y
respeta `postal.limits.body_max_chars`.

### Aprobar y cerrar

`POST /doll-requests/{id}/drafts/{draftId}/approve` → `status = completed`, y el sistema crea una
`letter` con **`author_id = client_id`** y `doll_request_id` referenciado (crédito a la Doll, propiedad
del cliente). El canal pasa a solo lectura. El cliente luego la envía por el flujo normal de
`entregas-buzon.md`.

**Sólo el cliente** aprueba (la Doll recibe 404). Las tres cosas ocurren en una transacción: se crea la
carta, se sella el borrador (`draft_approved_at`) y la solicitud pasa a `completed`. Un reintento no
puede acuñar una segunda carta: la solicitud ya está cerrada → `403 CHANNEL_CLOSED`.

Devuelve `201` con la **carta** (`LetterResource`), no con la solicitud: es lo que el cliente necesita
a continuación. Nace desbloqueada (`is_locked: false`) — aprobar no envía nada, nada en este producto
se salta el tiempo de tránsito.

Errores: `422 NOT_A_DRAFT` (el mensaje no es de tipo `draft`), `409 DRAFT_ALREADY_APPROVED`,
`403 CHANNEL_CLOSED`, `404` (borrador de otra solicitud, o no eres el cliente).

## POST /doll-requests/{id}/rate

Sólo el cliente, una sola vez, y sólo sobre una solicitud `completed`.

```json
{ "rating": 5, "comment": "Entendió lo que yo no sabía decir." }
```

`rating` entero 1–5, `comment` opcional (máx. 1000). Errores: `409 ALREADY_RATED`,
`409 INVALID_STATE_TRANSITION` (no está completada), `404` (no eres el cliente), `422` fuera de rango.

La respuesta es la **solicitud**, con `client_rating`/`client_rating_comment`/`rated_at`. El perfil
público no cambia todavía: el agregado lo recalcula `RecalculateDollRatingsJob` cada hora.

## Salvaguardas del rol Doll

- Verificación manual antes de activar (`doll_profiles.verified_at`).
- **La Doll no ve el buzón, ni el historial, ni los contactos del cliente.** Solo el brief y el chat.
- **La Doll no puede iniciar contacto.** Siempre responde a una solicitud.
- Filtro anti-intercambio de contactos: detecta emails, teléfonos y @handles en los mensajes y avisa.
  Protege a ambas partes y protege el modelo de negocio. → `ContactExchangeGuard` (3C).
- Botón de reporte dentro del chat. → `POST /reports` con `reportable_type: doll_chat_message`, que
  exige ser parte de la solicitud (3C).
- Los chats se purgan 90 días tras cerrar la solicitud (declarado en los términos).
  → `PurgeOldDollChatsJob` (diario 04:00, ventana en `config/dolls.php`).

## Pagos (fase 3, opcional)

`payment_status`: `not_required` | `pending` | `held` | `released` | `refunded`.
Stripe Connect Express; se retiene al aceptar, se libera al completar.

**Recomendación fase 1:** Dolls voluntarias con `rate_type = free`. Elimina toda la complejidad legal
y fiscal y permite validar el módulo antes de invertir en pagos.
