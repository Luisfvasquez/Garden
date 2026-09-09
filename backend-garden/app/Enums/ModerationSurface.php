<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a piece of text is coming from. The driver tunes its sensitivity per
 * surface: random letters and blog posts are moderated proactively and hard,
 * Doll chat only screens for PII / contact exchange, directed letters between
 * acquaintances are not proactively moderated at all
 * (backend-garden/docs/moderacion.md).
 */
enum ModerationSurface: string
{
    case RandomLetter = 'random_letter';
    case BlogPost = 'blog_post';
    case BlogComment = 'blog_comment';
    case DollChat = 'doll_chat';

    /** Free text sent to a stranger: PII / contact exchange is blocked outright. */
    public function blocksContactExchange(): bool
    {
        return $this === self::RandomLetter;
    }

    /** Doll chat only warns both parties and logs; it does not block. */
    public function warnsOnContactExchange(): bool
    {
        return $this === self::DollChat;
    }
}
