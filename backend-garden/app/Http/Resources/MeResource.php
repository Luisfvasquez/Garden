<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Private view of the authenticated user. The only place `email` is exposed.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pen_name' => $this->pen_name,
            'email' => $this->email,
            'postal_handle' => $this->postal_handle,
            'avatar_url' => $this->avatarUrl(),
            'bio' => $this->bio,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'email_verified' => $this->email_verified_at !== null,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'accepts_random_letters' => $this->accepts_random_letters,
            // Filled in by later modules (Dolls, Mailbox).
            'has_doll_profile' => false,
            'unread_mailbox_count' => 0,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
