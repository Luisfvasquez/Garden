# ADR-0004 — Moderación antes que botella al mar

**Estado:** aceptado

## Contexto

El módulo de cartas aleatorias es el corazón emocional del producto y también su mayor riesgo: enviar
texto libre a un desconocido es un vector directo de acoso, spam y contenido ilegal.

## Decisión

**El módulo aleatorio no se activa hasta que la moderación esté operativa.** Concretamente:

- Filtro automático obligatorio antes de encolar cualquier carta aleatoria.
- Opt-in explícito del receptor (`accepts_random_letters`, default `false`).
- Cuotas de emisión y de recepción.
- Sin adjuntos.
- Reporte accesible desde cada carta, con `restrict_random` automático a los 2 reportes confirmados.

Las cartas **dirigidas** entre usuarios que ya se conocen pueden moderarse de forma reactiva (solo por
reporte). Esta distinción es la que hace viable el módulo aleatorio.

## Consecuencias

- El orden del roadmap está condicionado: moderación es fase 2, junto con el módulo aleatorio, nunca
  después.
- Coste de latencia: el filtro corre en cola, la carta pasa unos segundos en `queued` antes de despachar.
  Irrelevante en un producto donde el tránsito dura horas.
- **Ninguna de estas restricciones se retira "para simplificar".** Si se retiran, lo que ocurre después
  es que hay que apagar el módulo.
