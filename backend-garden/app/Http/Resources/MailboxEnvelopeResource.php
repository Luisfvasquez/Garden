<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LetterDelivery;
use App\Support\SenderView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The closed envelope in a recipient's mailbox. Before the letter is opened
 * its content does not travel — only what is printed on the outside
 * (docs/api/entregas-buzon.md).
 *
 * @mixin LetterDelivery
 */
class MailboxEnvelopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $style = $this->letter->style;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'is_opened' => $this->isOpened(),
            'is_archived' => $this->isArchived(),
            'is_favorite' => $this->is_favorite,
            'is_anonymous' => SenderView::isHidden($this->resource),
            'envelope' => [
                'paper' => $style['paper'] ?? null,
                'seal' => $style['seal'] ?? null,
                'stamp' => $style['stamp'] ?? null,
            ],
            'sender' => SenderView::summary($this->resource),
            'title' => $this->letter->title,
            'has_attachments' => ($this->letter->attachments_count ?? $this->letter->attachments->count()) > 0,
            'in_reply_to_delivery_id' => $this->letter->in_reply_to_delivery_id,
            'delivered_at' => $this->delivered_at?->toIso8601ZuluString(),
        ];
    }
}
