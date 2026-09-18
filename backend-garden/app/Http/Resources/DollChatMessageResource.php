<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DollChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A chat line as both parties see it. The sender is identified by
 * `postal_handle` — the same public identifier used everywhere else. `system`
 * messages have no sender at all.
 *
 * @mixin DollChatMessage
 */
class DollChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doll_request_id' => $this->doll_request_id,
            'type' => $this->type->value,
            'body' => $this->body,
            'sender' => $this->when($this->sender !== null, fn () => new PartyResource($this->sender)),
            'is_mine' => $this->sender_id !== null
                && $this->sender_id === $request->user()?->getKey(),
            'draft_payload' => $this->draft_payload,
            'draft_version' => $this->draft_version,
            'draft_approved_at' => $this->draft_approved_at?->toIso8601ZuluString(),
            // Non-empty means the contact-exchange filter flagged this line.
            'pii_flags' => $this->pii_flags ?? [],
            'read_at' => $this->read_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
