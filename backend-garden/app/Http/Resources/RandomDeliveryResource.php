<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LetterDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The response to `POST /letters/{id}/send-random`. The recipient is always
 * `null` in this flow — the stranger is never revealed to the sender
 * (docs/api/botella-al-mar.md).
 *
 * @mixin LetterDelivery
 */
class RandomDeliveryResource extends JsonResource
{
    public function __construct(LetterDelivery $resource, private readonly int $quotaRemainingToday)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'estimated_delivery_at' => $this->estimated_delivery_at?->toIso8601ZuluString(),
            'recipient' => null,
            'quota_remaining_today' => $this->quotaRemainingToday,
            'held_for_review' => $this->status->value === 'held',
        ];
    }
}
