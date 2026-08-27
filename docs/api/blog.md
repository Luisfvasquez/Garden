# API — Blog y cartas al vacío

Estado: `[ ] contrato definido` · `[ ] backend` · `[ ] front`

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
