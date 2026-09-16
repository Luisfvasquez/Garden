# API — Auto Memory Dolls

Estado: `[x] contrato definido` · `[~] backend (3A, 3B)` · `[~] front (3A)`

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
POST   /api/v1/doll-requests/{id}/complete
POST   /api/v1/doll-requests/{id}/cancel
POST   /api/v1/doll-requests/{id}/rate

GET    /api/v1/doll-requests/{id}/messages    # cursor, orden ascendente
POST   /api/v1/doll-requests/{id}/messages
POST   /api/v1/doll-requests/{id}/drafts
POST   /api/v1/doll-requests/{id}/drafts/{draftId}/approve

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

### POST /doll-requests/{id}/messages

```json
{ "type": "text", "body": "¿Cómo era él cuando eran niños?" }
```

### POST /doll-requests/{id}/drafts

La Doll comparte un borrador versionado:

```json
{ "draft_payload": { "title": "...", "body": { "type": "doc", "content": [] }, "version": 3 } }
```

### Aprobar y cerrar

`POST /drafts/{draftId}/approve` → `status = completed`, y el sistema crea una `letter` con
**`author_id = client_id`** y `doll_request_id` referenciado (crédito a la Doll, propiedad del cliente).
El canal pasa a solo lectura. El cliente luego la envía por el flujo normal de `entregas-buzon.md`.

## Salvaguardas del rol Doll

- Verificación manual antes de activar (`doll_profiles.verified_at`).
- **La Doll no ve el buzón, ni el historial, ni los contactos del cliente.** Solo el brief y el chat.
- **La Doll no puede iniciar contacto.** Siempre responde a una solicitud.
- Filtro anti-intercambio de contactos: detecta emails, teléfonos y @handles en los mensajes y avisa.
  Protege a ambas partes y protege el modelo de negocio.
- Botón de reporte dentro del chat.
- Los chats se purgan 90 días tras cerrar la solicitud (declarado en los términos).

## Pagos (fase 3, opcional)

`payment_status`: `not_required` | `pending` | `held` | `released` | `refunded`.
Stripe Connect Express; se retiene al aceptar, se libera al completar.

**Recomendación fase 1:** Dolls voluntarias con `rate_type = free`. Elimina toda la complejidad legal
y fiscal y permite validar el módulo antes de invertir en pagos.
