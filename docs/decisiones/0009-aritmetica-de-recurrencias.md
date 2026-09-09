# ADR-0009 — Aritmética de recurrencias y zonas horarias

**Estado:** aceptado

## Contexto

`OccurrenceGenerator` convierte un `letter_schedule` en su lista de fechas. Hay tres puntos donde una
implementación ingenua se rompe con el tiempo:

1. **Precalcular UTC.** Guardar los 10 timestamps UTC al crear la programación falla cuando un país
   cambia de huso o mueve el horario de verano (ha pasado). La verdad es *fecha local + hora local +
   zona*; el instante UTC se deriva al materializar.
2. **29 de febrero.** Un aniversario anual anclado en 29-feb no existe tres de cada cuatro años.
3. **Meses de distinta longitud.** Un mensual anclado el día 31 no tiene "31 de febrero".

## Decisión

- **Nunca se persiste UTC de las ocurrencias futuras.** El `letter_schedule` guarda `anchor_date`
  (date), `local_time` (`HH:MM`) y `timezone`, **congelada al crear**. El instante se calcula en el
  momento de materializar:
  `CarbonImmutable::parse($date, $tz)->setTimeFromTimeString($localTime)->utc()`.
  Consecuencia buscada: 09:00 locales siguen siendo 09:00 locales aunque el offset UTC cambie por el
  horario de verano.

- **`leap_day_policy`** (`feb_28` | `mar_01`) para anclas en 29-feb, resuelto con el método de Carbon
  adecuado — sin condicionales de calendario:
  - `feb_28` → `addYearsNoOverflow($n)` (29-feb → 28-feb en año común).
  - `mar_01` → `addYears($n)` (el desbordamiento natural de Carbon lleva 29-feb → 1-mar).
  - Para cualquier fecha que sí existe en el mes destino ambos métodos son idénticos, así que la regla
    solo afecta al caso 29-feb.

- **Mensual**: `addMonthsNoOverflow($n)` desde el ancla. El día 31 se **recorta** al último día de los
  meses más cortos (31-ene → 28/29-feb → 31-mar). No hay opción de "saltar el mes"; si alguien la pide,
  se modela con `custom_dates`.

- **`custom_dates`**: lista explícita de `Y-m-d`, ordenada y deduplicada por el generador. `local_time`
  y `timezone` de la programación se aplican a todas.

- El generador es **puro** (sin acceso a base de datos). La vista de línea de tiempo y
  `GenerateUpcomingDeliveriesJob` lo consumen igual; se testea con `travelTo()` sobre fronteras de DST.

## Consecuencias

- `GET /schedules/{id}/occurrences` mezcla ocurrencias materializadas (con `delivery_id`) y virtuales
  (calculadas al vuelo). Ver ADR-0002.
- Mover de zona horaria **no recalcula** nada automáticamente: la zona está congelada. La UI avisa y
  ofrece recrear la programación (fuera del alcance de Fase 2).
- El recorte mensual es una decisión de producto, no un bug. Documentada aquí y en
  `docs/api/programaciones.md`.
