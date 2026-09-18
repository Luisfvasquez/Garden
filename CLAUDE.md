# Garden — Instrucciones para el agente

Monorepo del proyecto **Evergarden**: plataforma de correspondencia escrita inspirada en
*Violet Evergarden*. Backend Laravel desacoplado + PWA en Vue 3 + futura app móvil sobre la misma API.

## Estructura

```
garden/
├── docs/                  ← FUENTE DE VERDAD compartida. Contrato de API y decisiones.
│   ├── 00-especificacion-tecnica.md
│   ├── api/               ← Un archivo por módulo. Lo consumen backend y front.
│   ├── decisiones/        ← ADRs. Leer antes de cambiar algo "obvio".
│   └── progreso.md        ← Estado real del proyecto. Actualizar al cerrar cada tarea.
├── backend-garden/        ← Laravel 11 API
└── front-garden/          ← Vue 3 PWA
```

## Qué leer antes de qué

| Vas a... | Lee primero |
| --- | --- |
| Crear o modificar cualquier endpoint | `docs/api/_convenciones.md` + el archivo del módulo |
| Tocar cartas, entregas o el buzón | `docs/api/cartas.md`, `docs/api/entregas-buzon.md` |
| Tocar el ciclo de vida de una entrega | Sección 7 de `docs/00-especificacion-tecnica.md` |
| Trabajar en el módulo aleatorio | `docs/api/botella-al-mar.md` + ADR-0004 |
| Trabajar en Dolls | `docs/api/dolls.md` + ADR-0005 |
| Cambiar algo del despacho o las colas | `backend-garden/docs/jobs-y-colas.md` |
| Construir una vista | `front-garden/docs/sistema-diseno.md` + `docs/api/` del módulo |

**No cargues `00-especificacion-tecnica.md` entero salvo que lo pida explícitamente.** Es un documento
de referencia de ~2000 líneas; lee la sección concreta que necesites.

## Reglas transversales

1. **El contrato se escribe antes que el código.** Si un endpoint no está en `docs/api/`, primero lo
   documentas ahí, luego lo implementas. Si al implementar te desvías, actualizas el `.md` en el mismo
   commit.
2. **Todas las fechas en UTC** en persistencia y en las respuestas de la API (ISO-8601 con `Z`). La
   conversión a hora local ocurre solo en la capa de presentación.
3. **Nada de mensajería instantánea fuera del chat de Dolls.** Es la regla de producto más importante:
   todo lo demás se comunica por cartas. Si una funcionalidad la rompe, párate y pregunta.
4. **Toda funcionalidad que permita contactar a un desconocido** necesita opt-in explícito, cuota y
   moderación. Sin excepciones.
5. **Feature flags:** todo módulo nuevo nace detrás de un flag.
6. Al terminar una tarea, marca la casilla correspondiente en `docs/progreso.md`.
7. Si tomas una decisión técnica no obvia, escribe un ADR en `docs/decisiones/`.

## Prohibido sin preguntar

- Migraciones destructivas (drop de columna o tabla con datos).
- Guardar `letters.body` o `doll_chat_messages.body` sin cifrar.
- Exponer `email` o `sender_id` de una carta anónima en cualquier respuesta de la API.
- Devolver entregas en estado `in_transit` al destinatario.
- Quitar cuotas, límites o filtros de moderación "para simplificar".
- Añadir dependencias pesadas sin justificarlo.

## Comandos

```bash
# Backend
cd backend-garden && ./vendor/bin/pest
cd backend-garden && ./vendor/bin/pint
cd backend-garden && ./vendor/bin/phpstan analyse
docker compose up -d

# Front
cd front-garden && npm run dev
cd front-garden && npm run test
cd front-garden && npm run typecheck

# Regenerar tipos del front desde el contrato del backend
cd backend-garden && php artisan scramble:export --path=../docs/api/openapi.json
cd front-garden && npm run api:types   # → src/types/openapi.d.ts (NO api.d.ts, ver ADR-0014)
```
