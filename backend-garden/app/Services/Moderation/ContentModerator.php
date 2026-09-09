<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * The moderation contract. Drivers (`LocalModerator`, and later
 * `OpenAiModerator` / `PerspectiveModerator`) implement this and nothing else;
 * calling code never names a provider (backend-garden/docs/moderacion.md).
 *
 * Bound in `ModerationServiceProvider` from `config('moderation.driver')`.
 */
interface ContentModerator
{
    public function check(string $text, ModerationContext $context): ModerationVerdict;
}
