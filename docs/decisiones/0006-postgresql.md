# ADR-0006 — PostgreSQL sobre MySQL

**Estado:** aceptado

## Decisión

PostgreSQL 16.

## Motivos

1. **Índices parciales.** `letter_deliveries` crece indefinidamente, pero el trabajo real es siempre un
   subconjunto pequeño:
   ```sql
   CREATE INDEX idx_deliveries_dispatch ON letter_deliveries (status, scheduled_for)
       WHERE status = 'queued';
   ```
   El índice se mantiene pequeño aunque la tabla tenga millones de filas. MySQL no los soporta.
2. **`jsonb` con índices GIN** para `style`, `metadata`, `specialties`, `letters_map`.
3. **Full-text search nativo** (`tsvector`) para el blog en fase 2, sin infraestructura extra.
4. **Particionado por rango** de `scheduled_for`, necesario en fase 4 con cartas a 10 años vista.

## Consecuencias

- Los desarrollos locales usan Postgres, no SQLite, para no descubrir diferencias en producción.
- Los tests corren contra Postgres en CI.
