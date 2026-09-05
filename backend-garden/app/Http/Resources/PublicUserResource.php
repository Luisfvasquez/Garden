<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile: name/pen name, avatar, bio, country, join month. Never email,
 * never activity or counts (docs/api/auth.md).
 *
 * @mixin User
 */
class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'postal_handle' => $this->postal_handle,
            'display_name' => $this->displayName(),
            'avatar_url' => $this->avatarUrl(),
            'bio' => $this->bio,
            'country_code' => $this->country_code,
            'member_since' => $this->created_at?->format('Y-m'),
            'accepts_random_letters' => $this->accepts_random_letters,
            // Wired to the blocks module in Fase 1; until then nobody is blocked.
            'is_blocked_by_me' => false,
        ];
    }
}
