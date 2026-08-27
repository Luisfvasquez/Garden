# ADR-0002 — Generación perezosa de ocurrencias programadas

**Estado:** aceptado

## Contexto

Un schedule anual a 10 años podría materializar 10 filas de `letter_deliveries` al crearse. Con miles de
usuarios, la tabla se llena de filas que no se tocarán en años.

## Decisión

`GenerateUpcomingDeliveriesJob` corre a diario y materializa solo las ocurrencias de los próximos
**90 días**. La línea de tiempo que ve el usuario se calcula al vuelo desde el `letter_schedule`.

## Consecuencias

- Cambiar la carta del año 7 no obliga a regenerar nada.
- La tabla de entregas contiene solo trabajo real o inminente; los índices se mantienen pequeños.
- `GET /schedules/{id}/occurrences` mezcla ocurrencias materializadas (con `delivery_id`) y virtuales
  (sin él). El front debe tratar ambas.
- **Excepción:** los schedules póstumos se materializan completos y se congelan, porque dependen de que
  el usuario ya no esté para regenerarlos.

## Alternativa descartada

Materializar todo al crear. Simple, pero se degrada con el tiempo y complica cualquier edición.
