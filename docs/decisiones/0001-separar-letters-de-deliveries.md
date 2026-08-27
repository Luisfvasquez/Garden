# ADR-0001 — Separar `letters` de `letter_deliveries`

**Estado:** aceptado

## Contexto

La propuesta inicial tenía `recipient_id` tanto en `letters` como en `letter_deliveries`: dos fuentes de
verdad para el mismo dato.

## Decisión

`letters` guarda **solo contenido y estética**, sin destinatario. El destinatario, las fechas y el
estado viven exclusivamente en `letter_deliveries`. Una fila de entrega = una carta entregada a una
persona en una fecha.

## Consecuencias

- Multi-destinatario, programaciones, recurrencias y envío aleatorio salen gratis, sin duplicar texto.
- El estado (`queued`, `in_transit`, ...) es **por entrega**, no por carta. `letters` no tiene `status`.
- `letters.is_locked` marca la inmutabilidad una vez existe una entrega despachada.
- Consultar "mis cartas enviadas" implica siempre pasar por `letter_deliveries`.

**Es la decisión estructural más importante del proyecto. No la revierta nadie.**
