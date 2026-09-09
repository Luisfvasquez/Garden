<?php

declare(strict_types=1);

namespace App\Enums;

enum LetterKind: string
{
    case Direct = 'direct';
    case Random = 'random';
    case Unaddressed = 'unaddressed';
    case DollDraft = 'doll_draft';

    /** Random letters never carry attachments (abuse vector — cartas.md). */
    public function allowsAttachments(): bool
    {
        return $this !== self::Random;
    }
}
