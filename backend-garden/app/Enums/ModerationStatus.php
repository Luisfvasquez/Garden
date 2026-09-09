<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Moderation lifecycle for letters and posts (spec §7.3). Directed letters
 * between acquaintances default to `approved` (reactive moderation); random
 * letters and blog content must pass a filter first.
 */
enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Flagged = 'flagged';
    case Rejected = 'rejected';

    public function blocksDispatch(): bool
    {
        return match ($this) {
            self::Flagged, self::Rejected => true,
            default => false,
        };
    }
}
