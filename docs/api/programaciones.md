# API — Programaciones y envíos recurrentes

Estado: `[ ] contrato definido` · `[ ] backend` · `[ ] front`

Caso principal: *"una carta para cada cumpleaños de mi hija durante los próximos 10 años"*.

## Endpoints

```
GET    /api/v1/schedules
POST   /api/v1/schedules
GET    /api/v1/schedules/{id}
PATCH  /api/v1/schedules/{id}
DELETE /api/v1/schedules/{id}
GET    /api/v1/schedules/{id}/occurrences
PUT    /api/v1/schedules/{id}/occurrences/{date}/letter
POST   /api/v1/schedules/{id}/pause
POST   /api/v1/schedules/{id}/resume
```

## POST /schedules

```json
{
  "name": "Cumpleaños de Ann",
  "recipient": { "postal_handle": "ann-3f81" },
  "recurrence_type": "yearly",
  "anchor_date": "2027-04-12",
  "local_time": "09:00",
  "timezone": "America/New_York",
  "occurrences_total": 10,
  "letter_id": null,
  "leap_day_policy": "feb_28"
}
```

- `recurrence_type`: `once` | `yearly` | `monthly` | `weekly` | `custom_dates`.
- `letter_id` null = una carta distinta por ocurrencia (se asignan con `PUT .../occurrences/{date}/letter`).
- `timezone` se **congela** al crear. Si el usuario se muda, la UI le avisa y ofrece recalcular.
- `leap_day_policy`: `feb_28` | `mar_01`, para anchors en 29 de febrero.

## GET /schedules/{id}/occurrences

La vista de línea de tiempo. Devuelve todas las ocurrencias previstas, tengan carta asignada o no:

```json
{
  "data": [
    { "date": "2027-04-12", "local_time": "09:00", "letter": { "id": "01J8...", "title": "Doce años" },
      "delivery_id": null, "status": "pending" },
    { "date": "2028-04-12", "local_time": "09:00", "letter": null,
      "delivery_id": null, "status": "empty" },
    { "date": "2026-04-12", "local_time": "09:00", "letter": { "id": "..." },
      "delivery_id": "01J9...", "status": "delivered" }
  ],
  "meta": { "total": 10, "filled": 3, "delivered": 1 }
}
```

`status`: `empty` (falta carta) · `pending` · `queued` · `in_transit` · `delivered` · `cancelled`.

## Generación perezosa

**No se crean las 10 entregas de golpe.** `GenerateUpcomingDeliveriesJob` corre a diario y materializa
solo las ocurrencias de los próximos 90 días. Motivos: cambiar la carta del año 7 no obliga a regenerar
nada, y no llenas la tabla de filas muertas. Ver ADR-0002.

Excepción: los schedules con `trigger_type = 'posthumous'` se materializan completos y se congelan.

## Zonas horarias

Nunca precalcules UTC a 10 años vista (los husos y el horario de verano cambian). Guarda fecha + hora
local + zona, y convierte en el momento de generar:

```php
CarbonImmutable::parse($schedule->anchor_date)
    ->setTimeFromTimeString($schedule->local_time)
    ->setTimezone($schedule->timezone)
    ->addYears($i)
    ->utc();
```

## Cartas póstumas (fase 4, campo previsto desde ya)

`trigger_type`: `date` | `inactivity`. Con `inactivity`, tras N meses sin sesión se envían 3 avisos por
correo espaciados; si no hay respuesta, se despachan. **Requiere aviso legal y doble confirmación.**
No implementar en fase 1.
