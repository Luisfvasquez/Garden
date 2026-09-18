<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentStatus;
use App\Enums\ModerationStatus;
use App\Enums\PostType;
use App\Enums\PostVisibility;
use Database\Factories\PublicPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A blog post (docs/api/blog.md). Public bodies, so not encrypted. Real
 * authorship is always kept; `is_anonymous` only changes what the API resource
 * exposes.
 *
 * @property string $id
 * @property string $author_id
 * @property PostType $type
 * @property string $slug
 * @property string $title
 * @property array<string, mixed> $body
 * @property string $body_plain
 * @property string|null $testimonial
 * @property bool $is_anonymous
 * @property bool $comments_enabled
 * @property PostVisibility $visibility
 * @property ModerationStatus $moderation_status
 * @property string|null $source_delivery_id
 * @property ConsentStatus $consent_status
 * @property Carbon|null $consent_responded_at
 * @property Carbon|null $consent_denied_until
 * @property Carbon|null $published_at
 * @property-read User $author
 * @property-read LetterDelivery|null $sourceDelivery
 */
class PublicPost extends Model
{
    /** @use HasFactory<PublicPostFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type', 'title', 'body', 'body_plain', 'testimonial',
        'is_anonymous', 'comments_enabled', 'visibility',
    ];

    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'body' => 'array',
            'is_anonymous' => 'boolean',
            'comments_enabled' => 'boolean',
            'visibility' => PostVisibility::class,
            'moderation_status' => ModerationStatus::class,
            'consent_status' => ConsentStatus::class,
            'consent_responded_at' => 'datetime',
            'consent_denied_until' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PublicPost $post): void {
            $post->slug ??= self::generateSlug($post->title);
        });
    }

    public static function generateSlug(string $title): string
    {
        $base = Str::slug((string) Str::of($title)->ascii()->limit(60, '')) ?: 'post';

        do {
            $slug = $base.'-'.bin2hex(random_bytes(3));
        } while (static::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null
            && $this->moderation_status === ModerationStatus::Approved
            && ! $this->consent_status->blocksPublication();
    }

    public function publishIfReady(): void
    {
        if ($this->published_at === null && $this->isReadyToPublish()) {
            $this->forceFill(['published_at' => now()])->save();
        }
    }

    public function isReadyToPublish(): bool
    {
        return $this->moderation_status === ModerationStatus::Approved
            && ! $this->consent_status->blocksPublication();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<LetterDelivery, $this>
     */
    public function sourceDelivery(): BelongsTo
    {
        return $this->belongsTo(LetterDelivery::class, 'source_delivery_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, 'post_id');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Visible to the public: published, public visibility, not soft-deleted.
     *
     * @param  Builder<PublicPost>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('moderation_status', ModerationStatus::Approved)
            ->where('visibility', PostVisibility::Public);
    }

    /**
     * The ranked result set for a full-text search, as a subquery.
     *
     * It has to be a subquery, and that is the non-obvious part. The rank is a
     * computed expression, but cursor pagination builds its cursor by
     * comparing the ORDERED COLUMNS of the last row — and it emits those as
     * real column references. A computed `search_rank` in the outer ORDER BY
     * makes Postgres fail with a type mismatch on page 2 (it did; there is a
     * test). Wrapping the ranking in a derived table called `public_posts`
     * turns `search_rank` into an actual column of the thing being paged, so
     * the cursor composes.
     *
     * `websearch_to_tsquery`, not `to_tsquery`: it takes whatever a person
     * types — quotes, OR, a leading minus — and **never throws on malformed
     * input**. `to_tsquery` raises a syntax error on something as ordinary as
     * `cartas &`, turning a typo into a 500.
     *
     * Title is weighted A and body B by the trigger (migration 000550), so a
     * post named "Padre" outranks one that merely mentions it in passing.
     */
    public static function rankedSubquery(string $terms): QueryBuilder
    {
        $config = 'pg_catalog.spanish';

        return DB::table('public_posts')
            ->selectRaw(
                'public_posts.*, ts_rank(search_vector, websearch_to_tsquery(?, ?)) AS search_rank',
                [$config, $terms],
            )
            ->whereRaw('search_vector @@ websearch_to_tsquery(?, ?)', [$config, $terms]);
    }
}
