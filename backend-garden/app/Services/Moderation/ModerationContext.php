<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\ModerationSurface;

/**
 * The circumstances of a moderation check. Drivers use the surface to decide
 * how strict to be; `authorCountryCode` lets the caller surface the right
 * support resources when a self-harm signal shows up.
 */
final readonly class ModerationContext
{
    public function __construct(
        public ModerationSurface $surface,
        public ?string $authorId = null,
        public ?string $authorCountryCode = null,
        public string $locale = 'es',
    ) {}

    public static function for(ModerationSurface $surface): self
    {
        return new self($surface);
    }
}
