# ADR-0012 — Dolls voluntarias, sin pagos (Stripe diferido)

**Estado:** aceptado

## Contexto

`docs/00-especificacion-tecnica.md` §8.8 y `docs/api/dolls.md` §Pagos contemplan Stripe Connect Express
(retención al aceptar, liberación al completar) como algo opcional, y lo etiquetan explícitamente:
"Recomendación fase 1: Dolls voluntarias con `rate_type = free`. Elimina toda la complejidad legal y
fiscal y permite validar el módulo antes de invertir en pagos."

## Decisión

- El módulo de Dolls se lanza **sin integración de pagos**. `rate_type` acepta `per_letter`/`hourly` y
  las columnas `rate_amount`/`currency` existen en `doll_profiles` y `doll_requests`
  (`price_amount`/`currency`/`payment_status`), pero **nada las cobra ni las retiene**. Una Doll puede
  declarar una tarifa informativa; el cobro real, si llega, es un acuerdo fuera de la plataforma.
- `payment_status` en `doll_requests` (cuando se cree en el siguiente módulo) siempre vale
  `not_required` mientras esto no cambie.
- Stripe Connect Express queda como trabajo futuro explícito, no como deuda técnica oculta.

## Consecuencias

- Cero superficie de riesgo legal/fiscal en el lanzamiento del módulo (KYC, retenciones, impuestos,
  disputas).
- Si se activa el cobro más adelante, el modelo de datos ya tiene sitio (`rate_amount`, `currency`,
  `price_amount`, `payment_status`) — es una feature nueva sobre columnas existentes, no una migración
  destructiva.
- El directorio debe dejar claro en el front que `rate_type` distinto de `free` es **informativo**, para
  no generar expectativas de cobro automático que la plataforma no cumple todavía.
