<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LetterDelivery;
use App\Support\SenderView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The opened letter: full body and aesthetics, plus who sent it.
 *
 * @mixin LetterDelivery
 */
class MailboxLetterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $letter = $this->letter;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'is_archived' => $this->isArchived(),
            'is_favorite' => $this->is_favorite,
            'is_anonymous' => SenderView::isHidden($this->resource),
            'sender' => SenderView::summary($this->resource),
            'title' => $letter->title,
            'body' => $letter->body,
            'style' => (object) $letter->style,
            'word_count' => $letter->word_count,
            'reading_time_minutes' => $letter->readingTimeMinutes(),
            'attachments' => LetterAttachmentResource::collection($letter->attachments),
            'in_reply_to_delivery_id' => $letter->in_reply_to_delivery_id,
            'delivered_at' => $this->delivered_at?->toIso8601ZuluString(),
            'read_at' => $this->read_at?->toIso8601ZuluString(),
        ];
    }
}
