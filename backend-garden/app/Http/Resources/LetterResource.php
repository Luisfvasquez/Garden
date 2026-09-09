<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Letter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Letter
 */
class LetterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'style' => (object) $this->style,
            'kind' => $this->kind->value,
            'word_count' => $this->word_count,
            'reading_time_minutes' => $this->readingTimeMinutes(),
            'is_locked' => $this->is_locked,
            'moderation_status' => $this->moderation_status->value,
            'in_reply_to_delivery_id' => $this->in_reply_to_delivery_id,
            'attachments' => LetterAttachmentResource::collection($this->whenLoaded('attachments')),
            'deliveries_count' => $this->whenCounted('deliveries'),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
