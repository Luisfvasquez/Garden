# ADR-0008 — Moderación desacoplada del proveedor

**Estado:** aceptado

## Contexto

La moderación de contenido (obligatoria antes de encolar cartas aleatorias y de publicar en el blog —
ADR-0004) puede resolverse de muchas formas: listas locales, la API de moderación de OpenAI,
Perspective de Google, un modelo propio. Cada proveedor tiene su formato de petición y respuesta, su
latencia, su coste y su política de privacidad. Acoplar el dominio a uno concreto haría cara cualquier
migración y ataría decisiones de producto a decisiones de un tercero.

Además, los tests deben correr sin llamadas de red y de forma determinista.

## Decisión

El dominio depende **solo** de la interfaz `App\Services\Moderation\ContentModerator`:

```php
public function check(string $text, ModerationContext $context): ModerationVerdict;
```

- `ModerationContext` lleva la superficie (`random_letter`, `blog_post`, `blog_comment`, `doll_chat`),
  el autor y su país. La superficie determina la severidad: las aleatorias y el blog se moderan de
  forma proactiva y dura; el chat de Dolls solo detecta PII/intercambio de contactos; las cartas
  dirigidas entre conocidos no pasan por aquí (moderación reactiva).
- `ModerationVerdict` es un value object **igual para todos los drivers**: `decision`
  (`approved|flagged|rejected`), `categories` (`ModerationCategory`) y `score` 0..1.
- El driver activo se elige con `MODERATION_DRIVER` (`config/moderation.php`) y se enlaza en
  `ModerationServiceProvider`. Añadir `OpenAiModerator` o `PerspectiveModerator` es un `match` nuevo,
  cero cambios en el código que llama.

### Reglas de producto que la interfaz **no** puede romper

- `self_harm` nunca es `rejected` por el driver: se marca `flagged` y se escala. Bloquear la expresión
  del dolor es contraproducente en este producto (`docs/moderacion.md`). Quien llama decide:
  - carta **saliente** con señal de riesgo → mostrar `support_resources` del país y permitir continuar;
  - carta aleatoria **entrante** o post → retener para revisión humana + escalar.
- `minor_safety` sí es `rejected` siempre y escala fuera de la cola normal.
- El intercambio de contactos (email, teléfono, `@handle`, URL) se **bloquea** en cartas aleatorias y
  solo **avisa** en el chat de Dolls.

## Consecuencias

- `LocalModerator` (léxico + regex, `PiiScanner`) es el driver de lanzamiento: sin dependencias, corre
  toda la suite de tests de forma hermética. Sus listas viven en `config/moderation.php` y se amplían
  por despliegue.
- El registro de auditoría (`moderation_actions`, retención 2 años) y `ModerateContentJob` se
  implementan con sus consumidores (botella al mar y blog), no aquí: sin contenido real que moderar no
  aportan nada.
- Cambiar de driver no toca migraciones ni contrato de API. Si un driver nuevo añade categorías,
  se amplía el enum `ModerationCategory` y su mapeo a severidad.
