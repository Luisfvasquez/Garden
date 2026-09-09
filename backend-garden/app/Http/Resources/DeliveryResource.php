<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LetterDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery as seen by its **sender** (my outbox / send response). A `blocked`
 * delivery reports as `delivered` and its recipient block is never revealed
 * (ADR-0007).
 *
 * @mixin LetterDelivery
 */
class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'letter_id' => $this->letter_id,
            'status' => $this->status->asSeenBySender()->value,
            'tier' => $this->tier->value,
            'is_anonymous' => $this->is_anonymous,
            'scheduled_for' => $this->scheduled_for->toIso8601ZuluString(),
            'dispatched_at' => $this->dispatched_at?->toIso8601ZuluString(),
            'estimated_delivery_at' => $this->estimated_delivery_at?->toIso8601ZuluString(),
            'delivered_at' => $this->deliveredAtForSender(),
            'read_at' => $this->read_at?->toIso8601ZuluString(),
            'can_cancel' => $this->canCancel(),
            'recipient' => $this->recipientSummary(),
        ];
    }

    private function deliveredAtForSender(): ?string
    {
        // While in transit the sender must not see a delivery timestamp.
        return $this->status->isInMailbox() || $this->status->asSeenBySender()->isInMailbox()
            ? $this->delivered_at?->toIso8601ZuluString()
            : null;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function recipientSummary(): ?array
    {
        $recipient = $this->recipient;
        if ($recipient === null) {
            return null;
        }

        return [
            'display_name' => $recipient->displayName(),
            'postal_handle' => $recipient->postal_handle,
        ];
    }
}
