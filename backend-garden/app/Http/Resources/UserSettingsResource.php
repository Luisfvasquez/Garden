<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserSettings
 */
class UserSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'notify_email' => $this->notify_email,
            'notify_push' => $this->notify_push,
            'notify_on_arrival' => $this->notify_on_arrival,
            'notify_on_dispatch_confirm' => $this->notify_on_dispatch_confirm,
            'notify_on_doll_message' => $this->notify_on_doll_message,
            'notify_on_blog_comment' => $this->notify_on_blog_comment,
            'share_read_receipts' => $this->share_read_receipts,
            'quiet_hours_start' => $this->formatTime($this->quiet_hours_start),
            'quiet_hours_end' => $this->formatTime($this->quiet_hours_end),
            'theme' => $this->theme->value,
            'preferred_paper_style' => $this->preferred_paper_style,
            'show_transit_countdown' => $this->show_transit_countdown,
            // Canonical home is `users`; surfaced here for the client's convenience.
            'accepts_random_letters' => $user->accepts_random_letters,
            'random_letters_daily_cap' => $user->random_letters_daily_cap,
        ];
    }

    private function formatTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr($value, 0, 5);
    }
}
