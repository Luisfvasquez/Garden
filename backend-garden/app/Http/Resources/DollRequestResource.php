<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DollRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Both parties see the full request — it's their own transaction, and
 * `postal_handle` is already public everywhere else. Nothing here exposes the
 * client's mailbox, contacts or letter history (docs/api/dolls.md).
 *
 * @mixin DollRequest
 */
class DollRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'client' => $this->when($this->client !== null, fn () => [
                'postal_handle' => $this->client->postal_handle,
                'display_name' => $this->client->displayName(),
                'avatar_url' => $this->client->avatarUrl(),
            ]),
            'doll' => $this->when($this->doll !== null, fn () => [
                'postal_handle' => $this->doll->postal_handle,
                'display_name' => $this->doll->displayName(),
                'avatar_url' => $this->doll->avatarUrl(),
            ]),
            'occasion' => $this->occasion,
            'brief_notes' => $this->brief_notes,
            'target_recipient_hint' => $this->target_recipient_hint,
            'desired_tone' => $this->desired_tone,
            'deadline_at' => $this->deadline_at?->toIso8601ZuluString(),
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
            'accepted_at' => $this->accepted_at?->toIso8601ZuluString(),
            'started_at' => $this->started_at?->toIso8601ZuluString(),
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
            'rejected_at' => $this->rejected_at?->toIso8601ZuluString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'client_rating' => $this->client_rating,
            'client_rating_comment' => $this->client_rating_comment,
            'rated_at' => $this->rated_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
