# ADR-0014 — Los tipos del front siguen escritos a mano, con un guardián contra la deriva

**Estado:** aceptado

## Contexto

Desde la Fase 0, `front-garden/src/types/api.ts` arrastraba una nota: *"TEMPORAL. Se reemplaza por
`api.d.ts`, generado desde `openapi.json` (Scramble)"*. La Fase 4 trae Scramble, así que tocaba
cumplirla.

Al generar el documento y los tipos aparece el problema: **lo generado es más débil que lo escrito a
mano.** Scramble lee `$this->status->value` en un API Resource y lo único que puede afirmar es
`string`. No puede saber que el valor es uno de ocho literales.

```ts
// generado desde el código
status: string

// escrito a mano
status: 'pending' | 'accepted' | 'in_progress' | 'awaiting_client'
      | 'completed' | 'rejected' | 'expired' | 'cancelled'
```

De esas uniones depende código real: los `switch` exhaustivos, las claves de i18n
(`t(\`dolls.status.${status}\`)`) y la detección de erratas en tiempo de compilación. Sustituirlas por
`string` sería perder comprobaciones que hoy funcionan, no ganarlas.

## Decisión

No se reemplaza. Se separan las responsabilidades en tres ficheros:

| Fichero | Quién lo escribe | Qué es |
| --- | --- | --- |
| `docs/api/openapi.json` | Scramble, desde el código | Espejo del contrato real. Se versiona. |
| `src/types/openapi.d.ts` | `npm run api:types` | Superficie cruda generada. **No editar.** |
| `src/types/api.ts` | A mano | Lo que consume la app, más estrecho a propósito. |

Y se añade `src/types/contract.ts`, que en cada `npm run typecheck` comprueba que cada interfaz escrita
a mano tiene **exactamente las mismas claves** que su esquema generado. Si el backend añade, quita o
renombra un campo, el typecheck falla nombrando la clave:

```
error TS2322: Type 'boolean' is not assignable to type '{ MISSING_IN_FRONT: "mode" | "correspondence_opened"; }'.
```

El fichero generado se llama `openapi.d.ts`, no `api.d.ts` como decía el comando original de
`CLAUDE.md`: con `api.ts` y `api.d.ts` conviviendo, `import from '@/types/api'` resuelve al `.ts` y el
`.d.ts` quedaría muerto sin que nadie lo note.

## Consecuencias

- El tipado del front **no se degrada** y, además, la deriva silenciosa deja de ser posible. La primera
  ejecución del guardián ya encontró dos campos reales que faltaban: `DeliveryResource` expone `mode` y
  `correspondence_opened` desde la Fase 2 y el front nunca los declaró.
- Cuesta un paso manual: al cambiar un Resource hay que regenerar (`scramble:export` + `api:types`) y
  actualizar `api.ts`. El typecheck lo recuerda; no hay que acordarse.
- `docs/api/*.md` sigue siendo la fuente de verdad escrita (CLAUDE.md regla 1). `openapi.json` es lo que
  el código dice de sí mismo, y sirve para contrastar ambos.
- El test `ApiDocsTest` falla si una ruta `api/v1` no está en el `openapi.json` versionado, que es el
  modo real de estropear esto: añadir un endpoint y olvidar regenerar.
- Queda una imprecisión aceptada: `LetterStyleCatalogResource` se publica como un mapa genérico
  `dimensión → entradas[]`. Es honesto — el catálogo lo define `config/letter_styles.php` y añadir una
  dimensión no debería obligar a tocar una anotación. `contract.ts` no lo vigila.
- Si algún día Scramble infiere enums respaldados, esta decisión se puede revisar: el guardián ya
  garantiza que las claves coinciden, así que el cambio sería estrechar tipos, no descubrir campos.
