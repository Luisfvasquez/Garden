<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A recorded moderation consequence. Audit trail, 2-year retention
 * (backend-garden/docs/moderacion.md).
 */
enum ModerationActionType: string
{
    case RestrictRandom = 'restrict_random';
    case Suspend = 'suspend';
    case Warn = 'warn';
    case ContentRemoved = 'content_removed';
}
