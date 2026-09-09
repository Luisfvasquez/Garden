<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'is_anonymous' => $this->is_anonymous,
            'author' => $this->is_anonymous
                ? ['display_name' => $this->author->pen_name ?? 'Anónimo', 'postal_handle' => null]
                : ['display_name' => $this->author->displayName(), 'postal_handle' => $this->author->postal_handle],
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
