# Decisiones de arquitectura (ADR)

Un archivo por decisión no obvia. Formato corto: contexto, decisión, consecuencias.

**Para el agente:** antes de "simplificar" u "optimizar" algo que parezca raro, busca aquí. Estas
decisiones tienen motivo. Si crees que una está equivocada, propón un ADR nuevo que la supere en lugar
de cambiar el código en silencio.

| ADR | Título | Estado |
| --- | --- | --- |
| 0001 | Separar `letters` de `letter_deliveries` | Aceptado |
| 0002 | Generación perezosa de ocurrencias programadas | Aceptado |
| 0003 | Sin búsqueda abierta de usuarios | Aceptado |
| 0004 | Moderación antes que botella al mar | Aceptado |
| 0005 | El chat de Dolls es la única mensajería instantánea | Aceptado |
| 0006 | PostgreSQL sobre MySQL | Aceptado |
| 0007 | Bloqueo silencioso | Aceptado |
| 0008 | Moderación desacoplada del proveedor | Aceptado |
| 0009 | Aritmética de recurrencias y zonas horarias | Aceptado |
| 0010 | Cliente Redis `predis` y pool aleatorio | Aceptado |
| 0011 | Panel de moderación server-side (Filament) | Aceptado |
| 0012 | Dolls voluntarias, sin pagos (Stripe diferido) | Aceptado |
| 0013 | Reverb para el canal en tiempo real, al precio de Guzzle 7 | Aceptado |
| 0014 | Tipos del front a mano sobre el OpenAPI generado | Aceptado |
| 0015 | Dompdf en vez de un navegador headless para exportar cartas | Aceptado |
