<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Comment;
use App\Models\DollChatMessage;
use App\Models\LetterDelivery;
use App\Models\PublicPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The report target kinds exposed on the API (docs/api/comunidad-notificaciones.md).
 * Only the ones whose models exist are wired; the rest arrive with their modules.
 */
enum ReportableType: string
{
    case LetterDelivery = 'letter_delivery';
    case PublicPost = 'public_post';
    case Comment = 'comment';
    case User = 'user';
    case DollChatMessage = 'doll_chat_message';

    /**
     * Every kind on the enum is wired since Fase 3C. A type with no model
     * would have to be added here deliberately, and validation rejects
     * anything that isn't a case at all.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::LetterDelivery => LetterDelivery::class,
            self::User => User::class,
            self::PublicPost => PublicPost::class,
            self::Comment => Comment::class,
            self::DollChatMessage => DollChatMessage::class,
        };
    }
}
