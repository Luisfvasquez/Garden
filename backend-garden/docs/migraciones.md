# Migraciones

## Orden recomendado

1. `users`, `user_settings`, `password_reset_tokens`, `sessions`, `personal_access_tokens`
2. `feature_flags`, `support_resources`
3. `letters`, `letter_attachments`
4. `letter_deliveries`, `delivery_events`, `transit_routes`, `postal_holidays`
5. `letter_schedules`
6. `blocks`, `reports`, `moderation_actions`
7. `public_posts`, `comments`, `reactions`, `tags`, `taggables`
8. `doll_profiles`, `doll_requests`, `doll_chat_messages`
9. `notifications`, `push_subscriptions`
10. `jobs`, `failed_jobs`, `job_batches`

## Reglas

- **UUID** como PK en todas las tablas de dominio.
- **Compatibilidad hacia atrás obligatoria** con la app móvil en circulación: añadir columna → desplegar
  → migrar datos → eliminar columna en un despliegue **posterior**. Nunca en el mismo.
- Nunca `dropColumn` sobre datos en producción sin un ADR.
- Índices parciales de `letter_deliveries` en una migración propia y documentada (ver ADR-0006).
- Toda FK con `onDelete` explícito. Piensa qué pasa al borrar un usuario (ver spec §13.4).

## Índices críticos

```sql
CREATE INDEX idx_deliveries_dispatch ON letter_deliveries (status, scheduled_for)
    WHERE status = 'queued';
CREATE INDEX idx_deliveries_arrival  ON letter_deliveries (status, delivered_at)
    WHERE status = 'in_transit';
CREATE INDEX idx_deliveries_mailbox  ON letter_deliveries (recipient_id, status, delivered_at DESC);
CREATE INDEX idx_deliveries_sender   ON letter_deliveries (sender_id, created_at DESC);
```

## Seeds

`evergarden:seed-demo` debe crear un escenario coherente: usuarios con handles legibles, cartas en los
distintos estados, una programación con ocurrencias, posts del blog, una Doll verificada con una
solicitud en curso. Sin esto, probar el front es lentísimo.
