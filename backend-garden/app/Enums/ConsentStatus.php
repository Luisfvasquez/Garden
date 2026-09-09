<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether the original author of a shared letter has agreed to its publication
 * (docs/api/blog.md). `not_required` for every post type but `shared_letter`.
 */
enum ConsentStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Granted = 'granted';
    case Denied = 'denied';

    public function blocksPublication(): bool
    {
        return $this === self::Pending || $this === self::Denied;
    }
}
