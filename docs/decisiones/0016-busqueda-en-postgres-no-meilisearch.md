# ADR-0016 — La búsqueda vive en Postgres; Meilisearch no se ha ganado su sitio

**Estado:** aceptado

## Contexto

`docs/progreso.md` listaba "Búsqueda con Meilisearch" en la Fase 4, y la tabla de stack de la spec
(§ Arquitectura) apunta a Laravel Scout + Meilisearch. Pero la propia spec, en la sección del blog
(§8.7), dice otra cosa más concreta: *"Búsqueda full-text (Postgres `tsvector` en fase 1, Meilisearch en
fase 2)"*.

Y resulta que la fase 1 de esa frase **ya estaba construida y nadie la usaba**. La migración de 2D creó
en `public_posts` una columna `search_vector tsvector`, un índice GIN sobre ella y un trigger que la
mantiene al día. Infraestructura muerta desde hace dos fases.

Meilisearch, en cambio, es un **servicio nuevo**: un proceso más que desplegar, vigilar, respaldar y
mantener sincronizado con la base de datos, más Scout como capa intermedia. La contenerización sigue
diferida y hoy todo corre sobre Laragon.

## Decisión

La búsqueda se implementa sobre Postgres, activando lo que ya existía, y **no se instala Meilisearch**.

Con dos arreglos que se notaban de inmediato al encender el buscador:

1. **`pg_catalog.simple` → `pg_catalog.spanish`.** `simple` no lematiza: buscar "cartas" no encontraba
   "carta", ni "escribir" encontraba "escribiendo". El producto escribe en español.
2. **El título pesa más que el cuerpo.** `tsvector_update_trigger()` no sabe asignar pesos, así que hizo
   falta una función de trigger propia (`setweight(..., 'A')` para el título, `'B'` para el cuerpo). Un
   post titulado "Padre" debe salir por delante de otro que menciona "padre" de pasada.

Se consulta con `websearch_to_tsquery`, no con `to_tsquery`. Es la diferencia entre aceptar lo que la
gente escribe de verdad — comillas, `-palabra`, un `&` suelto — y convertir una errata en un error 500:
`to_tsquery('cartas &')` lanza una excepción de sintaxis.

## Consecuencias

- Cero servicios nuevos. Un `composer install` y funciona, también en CI.
- **El ranking obligó a una subconsulta**, y conviene saber por qué antes de "simplificarlo": el rango
  es una expresión calculada, pero la paginación por cursor construye su cursor comparando las columnas
  del ORDER BY y las emite como referencias a columnas reales. Un `search_rank` calculado en el ORDER BY
  externo hace que Postgres falle con un error de tipos **en la página 2**. Envolver el ranking en una
  tabla derivada llamada `public_posts` lo convierte en una columna de verdad. Hay un test que lo fija.
- **Una sola configuración por columna.** Al elegir `spanish`, los posts en inglés pierden lematización
  (siguen encontrándose por coincidencia exacta). Es el compromiso correcto para un producto cuya
  interfaz, recursos de ayuda y comunidad son hispanohablantes. Si el inglés crece, la salida es una
  segunda columna `search_vector_en` y elegir según `locale`, no necesariamente Meilisearch.
- Sin `ts_headline` (fragmentos resaltados): es caro sobre conjuntos grandes y el feed ya muestra el
  comienzo del texto. Es una mejora futura barata si se pide.
- **Cuándo reabrir esto:** búsquedas con tolerancia a erratas, facetado, sinónimos, o cuando el volumen
  haga que el GIN deje de rendir. Ninguna de las tres se da hoy. La búsqueda está encapsulada en
  `PublicPost::rankedSubquery()`, así que migrar a Scout sería sustituir un método.

## Lo que esta decisión NO cubre

**No se buscan cartas.** `letters.body` va cifrado (spec §13.1) y `body_plain` sólo existe "mientras es
necesario para moderación o búsqueda consentida". Buscar la correspondencia de alguien es una función
distinta, con su propio consentimiento explícito, y no se toca aquí.
