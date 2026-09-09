<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PublicPost;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicPost
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isAuthor = $viewer !== null && $viewer->getKey() === $this->author_id;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'testimonial' => $this->testimonial,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn (Tag $t) => [
                'slug' => $t->slug,
                'label' => $t->label,
            ])->all(), []),
            'is_anonymous' => $this->is_anonymous,
            'author' => $this->authorView(),
            'comments_enabled' => $this->comments_enabled,
            'published_at' => $this->published_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),

            // My own reactions, for the toggle UI. Counts are never exposed
            // publicly (docs/api/blog.md).
            'my_reactions' => $viewer === null ? [] : $this->reactions
                ->where('user_id', $viewer->getKey())
                ->pluck('type')
                ->map(fn ($t) => $t->value)
                ->values()
                ->all(),

            // Author-only bookkeeping.
            'moderation_status' => $this->when($isAuthor, fn () => $this->moderation_status->value),
            'consent_status' => $this->when($isAuthor, fn () => $this->consent_status->value),
            'consent_denied_until' => $this->when(
                $isAuthor && $this->consent_denied_until !== null,
                fn () => $this->consent_denied_until?->toIso8601ZuluString(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function authorView(): array
    {
        if ($this->is_anonymous) {
            return [
                'display_name' => $this->author->pen_name ?? 'Anónimo',
                'postal_handle' => null,
            ];
        }

        return [
            'display_name' => $this->author->displayName(),
            'postal_handle' => $this->author->postal_handle,
        ];
    }
}
