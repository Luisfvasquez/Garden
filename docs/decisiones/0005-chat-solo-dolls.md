# ADR-0005 — El chat de Dolls es la única mensajería instantánea

**Estado:** aceptado

## Contexto

La premisa del producto es que la gente se comunique por cartas, con espera. Un chat general destruiría
esa premisa en una semana: la gente usaría el chat y las cartas serían decoración.

## Decisión

- **No existe mensajería directa entre usuarios.**
- El único canal en tiempo real es `private-doll-request.{id}`, y solo mientras la solicitud está
  `in_progress` o `awaiting_client`.
- No hay canal público de cartas ni presencia global.
- La respuesta a una carta es otra carta, con su tiempo de tránsito.

## Consecuencias

- Reverb solo se usa para el chat de Dolls y para notificaciones personales
  (`private-user.{id}`).
- Cualquier funcionalidad futura que introduzca conversación instantánea entre usuarios necesita una
  justificación tan fuerte como la de las Dolls, y probablemente no la tenga.
- El filtro anti-intercambio de contactos en el chat de Dolls también protege esta regla: si la gente se
  pasa a WhatsApp, el producto pierde su razón de ser.
