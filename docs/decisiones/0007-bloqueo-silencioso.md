# ADR-0007 — Bloqueo silencioso

**Estado:** aceptado

## Contexto

Si el bloqueado descubre que lo han bloqueado, escala: crea otra cuenta, busca a la persona por otro
canal, o toma represalias. Es el patrón conocido en toda plataforma de mensajería.

## Decisión

Cuando A bloquea a B:

- Las entregas de B hacia A pasan a `status = 'blocked'` en base de datos.
- **B ve esas entregas como `delivered`.** No recibe error, ni aviso, ni indicio.
- A no recibe nada en su buzón.
- Las entregas aleatorias pendientes entre ambos se cancelan.

## Consecuencias

- El API Resource de entregas **debe traducir `blocked` a `delivered`** cuando el consumidor es el
  remitente. Es fácil olvidarlo al añadir un endpoint nuevo; añade un test que lo cubra.
- Las métricas internas sí distinguen ambos estados. Nunca las expongas en la API pública.
- El seguimiento postal (`/tracking`) debe generar eventos verosímiles hasta `delivered`.
