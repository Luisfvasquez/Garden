<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Message kinds inside a Doll chat (docs/00-especificacion-tecnica.md §
 * `doll_chat_messages`). `draft` carries a versioned `draft_payload`; `system`
 * is written by the platform itself (contact-exchange warnings, state notes)
 * and has no human sender.
 */
enum DollChatMessageType: string
{
    case Text = 'text';
    case Draft = 'draft';
    case System = 'system';
    case Attachment = 'attachment';

    /** Only the Doll may share a draft; only humans send text. */
    public function isSentByHuman(): bool
    {
        return $this !== self::System;
    }
}
