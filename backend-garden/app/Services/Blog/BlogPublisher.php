<?php

declare(strict_types=1);

namespace App\Services\Blog;

use App\Enums\ConsentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\ModerationStatus;
use App\Enums\ModerationSurface;
use App\Enums\PostType;
use App\Exceptions\ApiException;
use App\Models\Comment;
use App\Models\LetterDelivery;
use App\Models\PublicPost;
use App\Models\Tag;
use App\Models\User;
use App\Services\Moderation\ContentModerator;
use App\Services\Moderation\CriticalAlertDispatcher;
use App\Services\Moderation\ModerationContext;
use App\Support\TiptapContent;
use Illuminate\Support\Facades\DB;

/**
 * Creates blog posts and comments, running the mandatory pre-publication filter
 * and the consent handshake for shared letters (docs/api/blog.md, ADR-0004).
 *
 * Nothing is silently deleted: a `flagged` verdict holds the content invisible
 * for human review; a self-harm signal also surfaces support resources.
 */
class BlogPublisher
{
    private const MAX_TAGS = 3;

    public function __construct(
        private readonly ContentModerator $moderator,
        private readonly CriticalAlertDispatcher $alerts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPost(User $author, array $data): PublicPost
    {
        $type = PostType::from($data['type']);
        $body = TiptapContent::sanitize((array) ($data['body'] ?? []));
        $plain = TiptapContent::toPlainText($body);
        $testimonial = isset($data['testimonial']) ? trim((string) $data['testimonial']) : null;

        $source = $type->requiresConsent()
            ? $this->resolveConsentSource($author, (string) ($data['letter_delivery_id'] ?? ''))
            : null;

        $verdict = $this->moderator->check(
            trim($plain."\n".($testimonial ?? '')),
            new ModerationContext(ModerationSurface::BlogPost, authorId: $author->getKey(), authorCountryCode: $author->country_code, locale: $author->locale),
        );

        if ($verdict->decision->value === 'rejected') {
            throw new ApiException('La publicación no ha pasado el filtro de contenido.', 'CONTENT_FLAGGED', 422);
        }

        $flagged = $verdict->blocks();

        return DB::transaction(function () use ($author, $data, $type, $body, $plain, $testimonial, $source, $flagged, $verdict): PublicPost {
            $post = new PublicPost([
                'type' => $type,
                'title' => trim((string) $data['title']),
                'body' => $body,
                'body_plain' => $plain,
                'testimonial' => $testimonial !== '' ? $testimonial : null,
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                'comments_enabled' => (bool) ($data['comments_enabled'] ?? true),
                'visibility' => $data['visibility'] ?? 'public',
            ]);
            $post->author_id = $author->getKey();
            $post->moderation_status = $flagged ? ModerationStatus::Flagged : ModerationStatus::Approved;
            $post->consent_status = $source !== null ? ConsentStatus::Pending : ConsentStatus::NotRequired;
            $post->source_delivery_id = $source?->getKey();
            $post->save();

            if ($flagged) {
                $this->alerts->alertIfCritical(
                    'Blog post held for review',
                    $verdict,
                    "{$author->postal_handle}'s blog post was held for review.",
                    ['post_id' => $post->id, 'author_id' => $author->getKey()],
                );
            }

            $this->syncTags($post, (array) ($data['tags'] ?? []));

            $post->publishIfReady();

            return $post->refresh()->load('tags');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createComment(User $author, PublicPost $post, array $data): Comment
    {
        if (! $post->comments_enabled || ! $post->isPublished()) {
            throw new ApiException('Esta publicación no admite comentarios.', 'CHANNEL_CLOSED', 409);
        }

        $parentId = null;
        if (! empty($data['parent_id'])) {
            $parent = Comment::query()->where('post_id', $post->getKey())->whereKey($data['parent_id'])->first();
            if ($parent === null) {
                throw new ApiException('El comentario padre no existe.', 'NOT_FOUND', 404);
            }
            // One level only: a reply to a reply attaches to the top-level comment.
            $parentId = $parent->parent_id ?? $parent->getKey();
        }

        $bodyText = trim((string) $data['body']);
        $verdict = $this->moderator->check(
            $bodyText,
            new ModerationContext(ModerationSurface::BlogComment, authorId: $author->getKey(), authorCountryCode: $author->country_code, locale: $author->locale),
        );

        if ($verdict->decision->value === 'rejected') {
            throw new ApiException('El comentario no ha pasado el filtro de contenido.', 'CONTENT_FLAGGED', 422);
        }

        $flagged = $verdict->blocks();

        $comment = new Comment([
            'post_id' => $post->getKey(),
            'author_id' => $author->getKey(),
            'parent_id' => $parentId,
            'body' => $bodyText,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
        ]);
        $comment->moderation_status = $flagged ? ModerationStatus::Flagged : ModerationStatus::Approved;
        $comment->published_at = $flagged ? null : now();
        $comment->save();

        if ($flagged) {
            $this->alerts->alertIfCritical(
                'Blog comment held for review',
                $verdict,
                "{$author->postal_handle}'s comment was held for review.",
                ['comment_id' => $comment->id, 'post_id' => $post->id, 'author_id' => $author->getKey()],
            );
        }

        return $comment;
    }

    public function respondConsent(PublicPost $post, bool $granted): void
    {
        if ($post->consent_status !== ConsentStatus::Pending) {
            throw new ApiException('Esta solicitud ya se resolvió.', 'INVALID_STATE_TRANSITION', 409);
        }

        if ($granted) {
            $post->forceFill([
                'consent_status' => ConsentStatus::Granted,
                'consent_responded_at' => now(),
            ])->save();
            $post->publishIfReady();

            return;
        }

        $post->forceFill([
            'consent_status' => ConsentStatus::Denied,
            'consent_responded_at' => now(),
            'consent_denied_until' => now()->addDays(90),
        ])->save();
    }

    private function resolveConsentSource(User $author, string $deliveryId): LetterDelivery
    {
        $delivery = LetterDelivery::query()->whereKey($deliveryId)->first();

        if ($delivery === null
            || $delivery->recipient_id !== $author->getKey()
            || ! in_array($delivery->status, [DeliveryStatus::Delivered, DeliveryStatus::Read], true)) {
            throw new ApiException('No puedes compartir esa carta.', 'INVALID_TARGET', 422);
        }

        // A recent denial locks this letter for 90 days (docs/api/blog.md).
        $denied = PublicPost::withTrashed()
            ->where('source_delivery_id', $delivery->getKey())
            ->where('consent_status', ConsentStatus::Denied)
            ->where('consent_denied_until', '>', now())
            ->exists();

        if ($denied) {
            throw new ApiException('El autor rechazó publicar esta carta hace menos de 90 días.', 'CONSENT_REQUIRED', 403);
        }

        return $delivery;
    }

    /**
     * @param  list<string>  $labels
     */
    private function syncTags(PublicPost $post, array $labels): void
    {
        $labels = array_slice(array_values(array_unique(array_filter(array_map('trim', $labels)))), 0, self::MAX_TAGS);

        $ids = [];
        foreach ($labels as $label) {
            $tag = Tag::fromLabel($label);
            $tag->increment('usage_count');
            $ids[] = $tag->getKey();
        }

        $post->tags()->sync($ids);
    }
}
