<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The only reactions the blog allows — no generic "like" (docs/api/blog.md).
 * Counts are never shown publicly and never drive ranking.
 */
enum ReactionType: string
{
    case Heart = 'heart';
    case Tear = 'tear';
    case Flower = 'flower';
    case Candle = 'candle';
}
