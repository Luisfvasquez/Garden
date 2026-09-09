<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of blog publication (docs/api/blog.md). Only `shared_letter` — a
 * received letter republished with the recipient's testimony — needs the
 * original author's consent.
 */
enum PostType: string
{
    case SharedLetter = 'shared_letter';
    case UnaddressedLetter = 'unaddressed_letter';
    case Poem = 'poem';
    case Reflection = 'reflection';

    public function requiresConsent(): bool
    {
        return $this === self::SharedLetter;
    }
}
