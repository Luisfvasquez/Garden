# Moderación — implementación

> Reglas de producto en `../../docs/decisiones/0004-moderacion-antes-que-aleatorio.md` y arquitectura
> del driver en `../../docs/decisiones/0008-moderacion-driver-agnostica.md`.

**Estado (Fase 2A):** interfaz `ContentModerator` + value objects (`ModerationContext`,
`ModerationVerdict`) + enums (`ModerationCategory`, `ModerationDecision`, `ModerationSurface`) +
`LocalModerator` (léxico/regex de `config/moderation.php`) + `PiiScanner`, enlazados por
`MODERATION_DRIVER` en `ModerationServiceProvider`. `support_resources` + `GET /support-resources`
públicos, con seeder de líneas reales (ES, MX, AR, US + fallback internacional).
**Estado (Fase 2C):** `moderation_actions` (auditoría, 2 años) + `ModerationAction::restrictsRandom()` +
`RandomAbuseGuard` (2 reportes `actioned` sobre cartas aleatorias → `restrict_random` automático con
caducidad). Moderación **síncrona** en el envío de botella al mar (`RandomLetterSender`): `rejected` →
bloquea; `flagged` (autolesión) → `held` para revisión humana, nunca bloqueo silencioso.
**Estado (Fase 2F):** panel **Filament** en `/admin` (server-side, sesión `web`, ADR-0011). Acceso solo
`isStaff()` + `active`. Recursos: usuarios (suspender / restringir aleatorias / levantar), **cola de
reportes** ordenada por `severity` con «Confirmar» (`Report::markActioned()` → dispara
`RandomAbuseGuard`) / «Descartar», moderación del blog (posts `flagged` → aprobar/rechazar), catálogos
(`feature_flags`, `support_resources`, `tags`), y `PlatformStatsWidget` (usuarios, tránsito, salud del
reloj postal, moderación pendiente).
**Pendiente:** `ModerateContentJob` asíncrono + escalado real email/Slack; cola dedicada de comentarios
retenidos; recursos de `transit_routes`/`postal_holidays`.

## Capas

1. **Preventiva** — opt-in, verificación de email, antigüedad mínima, cuotas.
2. **Automática** — filtro al enviar/publicar.
3. **Reactiva** — reportes.
4. **Humana** — panel Filament con cola priorizada por `severity`.

## Interfaz

```php
interface ContentModerator
{
    public function check(string $text, ModerationContext $context): ModerationVerdict;
}
```

Drivers: `LocalModerator` (fase 1), `OpenAiModerator`, `PerspectiveModerator`.
Configurable con `MODERATION_DRIVER`. **Nunca acoples el código a un proveedor.**

`ModerationVerdict`: `approved` | `flagged` | `rejected` + categorías + puntuación.

## Qué se modera y cuándo

| Contenido | Momento | Modo |
| --- | --- | --- |
| Carta dirigida entre usuarios conocidos | – | Reactivo (solo por reporte) |
| Carta aleatoria | Antes de encolar | **Proactivo, obligatorio** |
| Post o comentario del blog | Antes de publicar | **Proactivo, obligatorio** |
| Mensaje de chat de Doll | Al enviar | Filtro de PII y contactos |

Esta distinción es la que hace viable el módulo aleatorio sin ahogar la correspondencia normal.

## Detección de PII / intercambio de contactos

Patrones de email, teléfono, @handles y URLs en:
- cartas aleatorias (bloquea),
- chat de Dolls (avisa a ambas partes y registra).

## Escalado crítico

`minor_safety` y `self_harm` con severidad `critical` → alerta inmediata al equipo, fuera de la cola
normal.

## Autolesión: qué NO hacer

El producto trata duelo, ruptura y enfermedad. El filtro rozará constantemente la detección de
autolesión y muchos positivos serán expresión legítima de dolor.

- Carta **saliente** con señales de riesgo: **no bloquear en silencio**. Mostrar recursos de ayuda del
  país del autor (`support_resources`) y permitir continuar.
- Carta **aleatoria entrante** con señales de riesgo: retener para revisión humana antes de entregarla a
  un desconocido.

Bloquear la expresión del dolor es contraproducente en este producto.

## Consecuencias automáticas

- 2 reportes confirmados sobre cartas aleatorias → `restrict_random`.
- Registro obligatorio en `moderation_actions` (auditoría legal, retención 2 años).
