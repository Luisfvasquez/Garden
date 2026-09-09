<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What triggered a moderation action (backend-garden/docs/moderacion.md).
 */
enum ModerationActionSource: string
{
    case Report = 'report';       // confirmed user reports
    case Filter = 'filter';       // automatic content filter
    case Manual = 'manual';       // a moderator acted by hand
}
