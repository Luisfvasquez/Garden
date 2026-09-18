# API — Blog y cartas al vacío

Estado: `[x] contrato definido` · `[x] backend` · `[~] front`

> **Backend (Fase 2D):** todo tras el flag `blog` (`feature:blog` → 404). `public_posts` (cuerpo **sin
> cifrar**, `body_plain` + `tsvector`/GIN para búsqueda), `comments` (un nivel; una respuesta a una
> respuesta se aplana al comentario raíz), `reactions` (`heart|tear|flower|candle`, **sin conteos
> públicos**), `tags` + `taggables`. `BlogPublisher` corre el **filtro obligatorio síncrono** antes de
> publicar: `rejected` → `422 CONTENT_FLAGGED`; `flagged` (autolesión incluida) → `202`, post
> `moderation_status=flagged` e invisible (revisión humana, nunca borrado). `PublicPostPolicy` /
> `CommentPolicy` con 404 sin fuga. `throttle:create-post` 5/h, `throttle:comment` 30/h.
>
> **Desviaciones respecto a este documento (actualizadas aquí):**
> - `POST /consent-requests/{id}/respond` devuelve `{ data: { id, consent_status, published } }` (no el
>   post completo). `{id}` es el **id del post**.
> - `POST /posts/{id}/request-consent` es idempotente: `202` si sigue `pending`, `409` si ya se
>   resolvió. La **notificación real** al autor original (con la vista previa) queda pendiente para
>   cuando se cablee Web Push / email en 2E; entretanto la solicitud se ve en `GET /consent-requests`.
> - `PATCH /posts/{id}` solo permite `title`, `testimonial`, `comments_enabled`, `tags` (cambiar el
>   cuerpo exigiría re-moderar).
> - `GET /posts` `sort=featured` aún ordena por fecha (el feed destacado curado a mano llega después).
> - `/feed.xml` (RSS) no implementado todavía.
> - Nuevos códigos de estado: comentar en un post con comentarios desactivados → `409 CHANNEL_CLOSED`.

## Endpoints

```
GET    /api/v1/posts                          # público. ?type=&tag=&sort=recent|featured  cursor
POST   /api/v1/posts
GET    /api/v1/posts/{slug}                   # público
PATCH  /api/v1/posts/{id}
DELETE /api/v1/posts/{id}
POST   /api/v1/posts/{id}/reactions           # { type }
DELETE /api/v1/posts/{id}/reactions/{type}
GET    /api/v1/posts/{id}/comments
POST   /api/v1/posts/{id}/comments
DELETE /api/v1/comments/{id}
GET    /api/v1/tags                           # público
POST   /api/v1/posts/{id}/request-consent
GET    /api/v1/consent-requests               # las que me piden a mí
POST   /api/v1/consent-requests/{id}/respond  # { granted: true|false }
```

## Tipos de publicación

| Tipo | Descripción | Consentimiento |
| --- | --- | --- |
| `shared_letter` | Carta **recibida** que el destinatario publica con su testimonio | **Requerido** |
| `unaddressed_letter` | Carta dirigida a nadie | No |
| `poem` | Micro-publicación literaria | No |
| `reflection` | Texto libre | No |

## POST /posts

```json
{
  "type": "shared_letter",
  "letter_delivery_id": "01J8...",
  "title": "La carta que me devolvió a mi padre",
  "body": { "type": "doc", "content": [] },
  "testimonial": "Nunca supe decirle esto en persona.",
  "tags": ["duelo", "perdón"],
  "is_anonymous": true,
  "comments_enabled": true,
  "visibility": "public"
}
```

- Máx. 3 etiquetas.
- `is_anonymous` muestra el `pen_name` o "Anónimo". **La autoría real se guarda siempre** para moderación.
- Si `type = shared_letter`, el post se crea con `sender_consent_status = pending` y **no se publica**
  hasta que el autor original acepte. Respuesta `202`, no `201`.

## Flujo de consentimiento

1. El destinatario crea el post → `sender_consent_status = pending`, `published_at = null`.
2. El autor original recibe notificación con **vista previa exacta de lo que se publicará** (incluido el
   testimonio y si será anónimo).
3. `POST /consent-requests/{id}/respond` con `granted: true` → se publica.
4. `granted: false` → el post queda `denied` y no se puede reintentar con la misma carta en 90 días.

Publicar sin consentimiento → `403` con `CONSENT_REQUIRED`.

## Reacciones

`heart` · `tear` · `flower` · `candle`. **Nada de "me gusta" genérico.**

Los conteos **no se muestran públicamente** ni generan ranking: el feed destacado es curado a mano, no
algorítmico. Es una decisión de producto, no una limitación técnica.

## Comentarios

- Anidamiento de 1 nivel máximo.
- El autor del post puede desactivarlos (`comments_enabled`).
- Anónimos permitidos, con autoría interna registrada.

## Moderación

**Todo post y comentario pasa por filtro automático antes de publicarse.** Lo marcado queda retenido
(`moderation_status = flagged`, invisible) hasta revisión humana.

Los temas de este producto (duelo, ruptura, enfermedad) rozan constantemente la detección de autolesión.
El filtro debe distinguir expresión legítima de dolor de riesgo real. Ante detección de riesgo la
respuesta **no es borrar**: se muestran recursos de ayuda al autor (`GET /support-resources?country_code=`)
y se escala a revisión humana.

## Extras

- Búsqueda full-text: `tsvector` de Postgres en fase 2, Meilisearch en fase 4.
- Feed RSS/Atom público en `/feed.xml` — barato y coherente con la estética.

## Búsqueda

```
GET /api/v1/posts?q=cartas%20a%20mi%20padre
```

Full-text sobre título y cuerpo, en Postgres (`tsvector` + GIN, configuración `spanish`). Ordena por
relevancia y se combina con `type` y `tag`. Ver **ADR-0016** para por qué no Meilisearch.

- El **título pesa más que el cuerpo**: un post titulado "Padre" sale por delante de otro que lo
  menciona de pasada.
- **Lematiza en español**: "cartas" encuentra "carta", "escribir" encuentra "escribiendo".
- Acepta `"frase exacta"` entre comillas y `-palabra` para excluir.
- **Cualquier cosa que alguien escriba es válida.** Se usa `websearch_to_tsquery`, así que un `&` suelto
  o un paréntesis sin cerrar devuelven resultados vacíos, no un 500.
- Sólo busca lo que ya es público: un borrador o un post retenido por moderación nunca aparece.
- Un `q` vacío o en blanco equivale a no buscar.

Limitación conocida: hay **una sola configuración de idioma por columna**, y es `spanish`. Los posts en
inglés se siguen encontrando por coincidencia exacta, pero sin lematización.

**No se buscan cartas privadas.** `letters.body` va cifrado; buscar correspondencia propia sería una
función aparte, con su propio consentimiento (spec §13.1).
