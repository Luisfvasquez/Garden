<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a moderation verdict flagged. Mirrors the report categories
 * (docs/api/comunidad-notificaciones.md) plus `pii` for contact-exchange
 * detection in random letters and Doll chat (backend-garden/docs/moderacion.md).
 */
enum ModerationCategory: string
{
    case Harassment = 'harassment';
    case Sexual = 'sexual';
    case Hate = 'hate';
    case Violence = 'violence';
    case SelfHarm = 'self_harm';
    case Spam = 'spam';
    case MinorSafety = 'minor_safety';
    case Pii = 'pii';
    case Other = 'other';

    /**
     * These never wait in the normal queue: they alert the team immediately
     * and, for outgoing letters, surface support resources to the author.
     */
    public function isCritical(): bool
    {
        return match ($this) {
            self::MinorSafety, self::SelfHarm => true,
            default => false,
        };
    }
}
