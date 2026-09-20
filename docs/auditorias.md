# Sesiones de auditoría

Cómo trabajar con el agente ahora que el proyecto está construido.

---

## Por qué separar auditar de arreglar

La auditoría previa al MVP encontró tres fallos reales que ninguna suite de tests había detectado: no
había forma de reportar nada desde el front, `POST /reports` no podía apuntar a una persona, y una
petición sin `Accept: application/json` devolvía 500 en vez de 401.

Los tres aparecieron porque se buscó **sin permiso para arreglar**. Un agente que puede arreglar lo que
encuentra tiende a encontrar lo que sabe arreglar: acota el hallazgo al tamaño del parche, y los
problemas grandes o incómodos se quedan sin nombrar.

**Regla:** una sesión encuentra o arregla. Nunca las dos.

---

## Estructura de una sesión de auditoría

1. **Sesión limpia.** `/clear` antes de empezar. Con 17 ADRs y decenas de archivos de contrato,
   arrastrar contexto degrada las respuestas.
2. **Un área, un documento de referencia.** «Audita X contra `docs/api/x.md`», no «revisa el proyecto».
3. **Prohibido escribir código.** Dilo explícitamente en el prompt.
4. **Salida en forma de lista con evidencia**: archivo, línea, por qué es un problema, gravedad.
5. **Triaje tuyo.** Decides qué entra y qué no.
6. **Sesión nueva por cada arreglo**, con el hallazgo concreto como entrada.

---

## Prompts que funcionan

### Cobertura de policies (la más valiosa pendiente)

```
Audita la autorización del backend. No escribas ni modifiques código.

1. Lista todas las rutas de routes/api/v1.php que leen o modifican datos de un usuario.
2. Para cada una, di qué Policy la protege y en qué test se comprueba que
   otro usuario recibe 403 o 404.
3. Marca las que no tengan ninguna de las dos cosas.

Salida: tabla con ruta | policy | test | hueco (sí/no). No propongas arreglos.
```

### Contrato contra implementación

```
Compara docs/api/entregas-buzon.md con la implementación real.
No modifiques nada.

Busca: endpoints documentados que no existan, endpoints que existan sin
documentar, campos de respuesta distintos a los del documento, y error_code
que el documento promete y el código no emite (o al revés).

Salida: lista de discrepancias con archivo y línea.
```

### Fugas de privacidad

```
Audita los API Resources contra las reglas de privacidad de
docs/api/_convenciones.md. No modifiques nada.

Verifica una por una:
- email nunca fuera de GET /me
- con is_anonymous=true, sender_id no viaja en ninguna respuesta
- ninguna entrega in_transit alcanza al destinatario, tampoco en conteos
- blocked se traduce a delivered para el remitente (ADR-0007)
- 404 en vez de 403 donde revelar la existencia ya sería filtración

Para cada regla: dónde se aplica, dónde podría no aplicarse, qué test la fija.
```

### Promesas incumplidas de la API

> **Ejecutada el 2026-09-20.** Resultado en `auditoria-contrato-vs-codigo.md`: tres de tres
> incumplidas. Si la repites, cambia el área — esta ya está cubierta.

```
Busca cosas que la API declara y no cumple. No modifiques nada.

Ejemplo del tipo que busco: DELETE /me responde 202 con deletes_at a 30 días
— ¿existe el job que borra de verdad y está programado?

Revisa endpoints que prometen efectos diferidos o asíncronos y confirma que
el efecto existe.
```

### Deriva de los ADRs

```
Lee docs/decisiones/ y comprueba si el código sigue respetando cada decisión.
No modifiques nada.

Para cada ADR: ¿sigue vigente en el código, se ha erosionado, o alguna
condición de reapertura (0016, 0017) ya se cumple?

Salida: ADR | estado | evidencia.
```

### Antes de tocar algo que no escribiste tú

```
Explícame cómo funciona [X] hoy, qué ADR lo cubre y qué se rompería si
cambiase [Y]. No modifiques nada.
```

---

## Auditorías hechas

Una línea por sesión. El documento de cada una guarda la evidencia con archivo y línea, para no
volver a rastrear lo mismo.

| Fecha | Área | Documento | Resultado |
| --- | --- | --- | --- |
| 2026-09-18 | Validación previa al MVP | (en el commit y en la cabecera de `progreso.md`) | 3 fallos reales, los tres arreglados |
| 2026-09-20 | Promesas incumplidas de la API | `auditoria-contrato-vs-codigo.md` | 3 huecos: borrado de cuenta a medias, `GET /me/export` inexistente, adjuntos sin visor. **Ninguno arreglado** — son casillas de 5D |

Las tres comparten forma, y conviene tenerla presente al escribir el próximo prompt: **la capa que
promete estaba terminada y la que cumple no existía**. Ninguna se detecta ejercitando la API, porque
todas responden exactamente lo que el contrato dice. Sólo aparecen siguiendo la promesa hasta su
consecuencia.

---

## Sesiones de arreglo

Una vez triado, cada arreglo va en su sesión:

```
Contexto: la auditoría encontró que [hallazgo concreto, con archivo y línea].

Arregla solo eso. Requisitos:
- Test que falle antes del arreglo y pase después.
- Si toca el contrato, actualiza docs/api/*.md en el mismo commit.
- Si implica una decisión no obvia, escribe el ADR.
- Actualiza la casilla de docs/progreso.md.
- No refactorices nada que no sea necesario para este arreglo.
```

Ese último punto importa. El refactor oportunista mete cambios sin revisar en un commit que creías
pequeño.

---

## Para añadir algo nuevo

El orden de `CLAUDE.md` sigue vigente: **el contrato antes que el código**.

```
Quiero añadir [funcionalidad]. Antes de escribir código:

1. Lee docs/decisiones/ y dime si choca con alguna decisión tomada.
2. Comprueba las cinco preguntas de la spec §16.3 (¿rompe "todo por cartas"?
   ¿abre contacto no consentido? ¿toca una máquina de estados? ¿se resuelve
   con un listener? ¿va detrás de un feature flag?).
3. Escribe o amplía docs/api/[modulo].md con endpoints y payloads.

Para ahí. El código va en otra sesión.
```

---

## Higiene con Claude Code

- **`/clear` entre tareas.** Una tarea, una sesión.
- **Deja que lea el `CLAUDE.md`**, no le pegues la spec entera: son ~2000 líneas y el índice existe
  justamente para que lea solo lo que necesita.
- **Pide evidencia, no conformidad.** «Enséñame el test que lo demuestra» descubre más que «¿está bien?».
- **Desconfía del acuerdo rápido.** Si propones algo que contradice un ADR y el agente acepta sin
  mencionarlo, no ha leído `docs/decisiones/`. Pídeselo explícitamente.
- **Commits pequeños.** Un arreglo, un commit, con la casilla de progreso en el mismo.
- **`docs/progreso.md` se actualiza al cerrar, no al empezar.** Una casilla marcada por optimismo es
  peor que una vacía, y eso ya está escrito en el propio checklist.
