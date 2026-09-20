# Auditoría: lo que la API declara y no cumple

**Fecha:** 2026-09-20 · **Alcance:** tres promesas concretas del contrato, verificadas contra el código.
**Método:** lectura. No se modificó nada.

> Complementa `auditorias.md` (procedimiento general). Esto es el resultado de una pasada concreta,
> con archivo y línea, para no repetir el rastreo la próxima vez.

| # | Promesa | Veredicto |
| --- | --- | --- |
| 1 | `DELETE /me` borra la cuenta a los 30 días, con anonimización §13.4 | **Existe a medias** — se apunta la fecha y no pasa nada más |
| 2 | `GET /me/export` (RGPD) | **No existe** — en ninguna capa |
| 3 | Visor de adjuntos en el front | **No existe** — el backend los sirve, el front nunca los pinta |

---

## 1. `DELETE /me` — existe a medias

Lo que responde es correcto. Lo que promete esa respuesta, no ocurre nunca.

### Qué sí existe

- `app/Http/Controllers/Api/V1/MeController.php:45-55` — escribe `deletes_at = now()+30d` y devuelve
  `202` con la fecha. Cumple el contrato de `docs/api/auth.md:252-256` al pie de la letra.
- `database/migrations/0001_01_01_000000_create_users_table.php:38` — la columna existe.
- `app/Services/Auth/CredentialChecker.php:36` — volver a entrar limpia `deletes_at`. El borrado es
  cancelable, que es lo que se espera de una gracia de 30 días.
- `app/Support/SenderView.php:37` — la **presentación** ya está resuelta: si el remitente no se puede
  resolver, el buzón del destinatario muestra `"Usuario eliminado"`.

### Qué no existe

**No hay job de borrado.** `ProcessAccountDeletionsJob` no aparece en `app/`, `routes/` ni
`database/`. `routes/console.php` no programa nada que mire `deletes_at`.

**`deletes_at` no se lee en ninguna consulta.** Las únicas apariciones en `app/` son las tres de
arriba: se escribe en `MeController`, se limpia en `CredentialChecker`, se castea en
`app/Models/User.php:102`. Nadie filtra por ella.

> `backend-garden/docs/jobs-y-colas.md:33` afirma que «la gracia de 30 días la aplica hoy la propia
> consulta». **Es falso.** No hay tal consulta. Esa línea hace creer que el mecanismo existe a medias
> cuando no existe en absoluto.

**No hay anonimización.** `UserStatus::Deleted` está declarado en el enum pero solo se **lee**, en
`CredentialChecker.php:40`. Nada lo escribe: ninguna cuenta llega nunca a ese estado.

**El test no cubre el borrado.** `tests/Feature/Me/AccountLifecycleTest.php:23-31` solo comprueba que
la fecha se escribe y se devuelve. Es coherente con lo implementado — el hueco no es del test.

### La trampa del esquema, para quien implemente esto

§13.4 dice que las cartas ya entregadas **permanecen en el buzón del destinatario**: «Son suyas». El
esquema está montado para que la implementación ingenua haga justo lo contrario:

```
letters.author_id            → cascadeOnDelete   (…create_letters_table.php:18)
letter_deliveries.letter_id  → cascadeOnDelete   (…create_letter_deliveries_table.php:19)
letter_deliveries.sender_id  → nullOnDelete      (…create_letter_deliveries_table.php:22)
```

Un `$user->delete()` borra sus `letters`, y la cascada se lleva por delante las `letter_deliveries`
**del destinatario**. El `nullOnDelete` de `sender_id` no llega a actuar: la fila de la carta
desaparece antes. Es decir: el borrado real destruye correspondencia ajena, que es exactamente lo
que §13.4 prohíbe.

La vía correcta es la que describe la spec: **anonimizar en sitio**, conservando la fila de `users`
(`name = "Usuario eliminado"`, email hasheado) y sin borrar nada duro. `SenderView` ya funciona con
las dos formas, así que la presentación no hay que tocarla.

### Un segundo hueco, menor pero real

`destroy()` **no revoca el acceso ni cambia el `status`**, mientras que `deactivate()`
(`MeController.php:32-42`) sí hace ambas cosas. Quien pide el borrado de su cuenta sigue con la sesión
abierta y plenamente operativo: puede seguir enviando cartas durante los 30 días. Puede ser deliberado
(la gracia es cancelable y cerrar la sesión la haría incómoda de revocar), pero no está escrito en
ningún sitio, y la asimetría entre los dos métodos no tiene comentario que la explique.

### Lo que falta, en orden

1. `ProcessAccountDeletionsJob` + su entrada en `routes/console.php`.
2. Anonimización en sitio, nunca `delete()`.
3. Cancelar las entregas programadas del usuario (§13.4).
4. Entregas pendientes **hacia** él → `failed` + avisar al remitente + devolver a borradores (§13.4).
5. Arreglar la línea falsa de `jobs-y-colas.md:33`.

Es una obligación legal declarada en la API, no una función pendiente: hoy la API promete un borrado
que no ocurre.

---

## 2. `GET /me/export` — no existe

En ninguna capa. No es «está a medias»: no hay ni el primer archivo.

| Dónde | Estado |
| --- | --- |
| `docs/api/auth.md:31` | Declarado en la lista de endpoints |
| `docs/00-especificacion-tecnica.md` §13.4 | Especificado: ZIP asíncrono, enlace firmado, 24 h |
| `backend-garden/routes/api/v1.php` | **Ausente** — no hay ruta |
| `app/` | **Ausente** — sin controlador, sin job, sin servicio |
| `docs/api/openapi.json` | **Ausente** (0 coincidencias) |
| `front-garden/src/types/openapi.d.ts` | **Ausente** (0 coincidencias) |
| `front-garden/src/` | **Ausente** — nada lo llama |
| `docs/progreso.md` | Sin casilla hasta la de Fase 5D, línea 402 |

Detalle que agrava el despiste: `docs/api/auth.md:3` declara el módulo como `[x] backend`, y dos
líneas más abajo, en `auth.md:5`, admite que el export queda pendiente «— Fase 2». Estamos en Fase 5.
Quien lea solo la cabecera concluye que el módulo está cerrado.

Lo bueno: al no estar en `openapi.json`, la deriva no llegó a los tipos del front (`contract.ts` no
tenía nada que vigilar). El contrato generado y el código coinciden; el que miente es el `.md` escrito
a mano.

**Mínimo para dejar de mentir:** marcar el endpoint como no implementado en `auth.md:31` y corregir la
cabecera de estado. Implementarlo es 5D.

---

## 3. Adjuntos en el front — no existe forma de verlos

El backend está completo. El front no tiene ni visor ni subida.

### El backend los entrega, y bien

- Rutas: `routes/api/v1.php:110-114` (subir y borrar).
- `app/Http/Resources/MailboxLetterResource.php:38` — la carta abierta incluye `attachments` con
  `url`, `type`, `mime_type`, `size_bytes` y `metadata`
  (`app/Http/Resources/LetterAttachmentResource.php:21-30`).
- `app/Http/Resources/MailboxEnvelopeResource.php:42` — el listado incluye `has_attachments`.
- `app/Models/LetterAttachment.php:54-57` — resuelve la URL del disco.

### El front no los usa

- `src/types/api.ts:103-113` — el tipo `LetterAttachment` existe.
- `src/types/api.ts:213` — `MailboxLetter.attachments` está declarado **no opcional**.
- `src/views/mailbox/MailboxReadView.vue:133-138` — la vista de lectura pinta `body`, `style` y
  `title`, el enlace al PDF y `SafetyActions`. **Nunca toca `letter.attachments`.**
- Búsqueda de `attachment` en `front-garden/src/`: fuera de los tipos y del OpenAPI generado, la única
  aparición es `src/components/mailbox/EnvelopeClosed.vue:52`.
- Búsqueda de `type="file"` / `FormData` en `src/`: solo `src/api/account.ts:24` (avatar). **No hay
  subida de adjuntos desde el front**, pese a que `progreso.md:69` da la función por hecha — esa
  casilla es del backend.

### Lo peor no es que falte: es que se anuncia

`EnvelopeClosed.vue:52` pinta un clip 📎 cuando `has_attachments` es true. El sobre cerrado le dice al
destinatario que hay algo adjunto; al abrirlo, no hay nada. La promesa se hace explícitamente y se
incumple en el clic siguiente.

Y no hay puerta trasera: el PDF tampoco los incluye (`LetterPdfRenderer` y `resources/views/` no
mencionan adjuntos). La única forma de ver un adjunto hoy es la URL cruda desde la respuesta JSON.

`progreso.md:107` ya lo anotaba como pendiente («Pendiente: visor de adjuntos, paginación cursor…») y
5D lo recoge en la línea 395. La auditoría lo confirma y añade dos cosas que esa nota no dice: **que no
hay subida tampoco**, y **que el sobre ya lo está anunciando**.

---

## Patrón común

Los tres huecos comparten forma: **la capa que promete está terminada y la que cumple no existe.**

- El `202` de `DELETE /me` es correcto; lo que anuncia no ocurre.
- `auth.md` lista `GET /me/export` entre los endpoints; no hay ruta.
- El sobre pinta un clip; no hay visor.

Ninguno se detecta ejercitando la API: los tres responden lo que el contrato dice. Solo aparecen
siguiendo la promesa hasta su consecuencia — que es lo que `auditorias.md` pide hacer.

De los tres, el único con plazo externo es el primero: un borrado de cuenta declarado y no ejecutado
es incumplimiento legal, no deuda técnica.
