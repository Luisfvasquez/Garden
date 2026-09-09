<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LetterDelivery;

/**
 * How a delivery's sender is presented to its recipient. Anonymity hides the
 * sender entirely until `reveal_sender_at` (if set) has passed — the sender's
 * id and handle never travel to the client while hidden (docs/api/_convenciones.md).
 */
final class SenderView
{
    public static function isHidden(LetterDelivery $delivery): bool
    {
        if (! $delivery->is_anonymous) {
            return false;
        }

        return $delivery->reveal_sender_at === null || $delivery->reveal_sender_at->isFuture();
    }

    /**
     * @return array{display_name: string, postal_handle: string|null, avatar_url: string|null}
     */
    public static function summary(LetterDelivery $delivery): array
    {
        if (self::isHidden($delivery)) {
            return ['display_name' => 'Alguien', 'postal_handle' => null, 'avatar_url' => null];
        }

        $sender = $delivery->sender;

        return [
            'display_name' => $sender?->displayName() ?? 'Usuario eliminado',
            'postal_handle' => $sender?->postal_handle,
            'avatar_url' => $sender?->avatarUrl(),
        ];
    }
}
