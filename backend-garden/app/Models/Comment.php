<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A blog comment. One level of nesting: `parent_id` always points at a
 * top-level comment (docs/api/blog.md).
 *
 * @property string $id
 * @property string $post_id
 * @property string $author_id
 * @property string|null $parent_id
 * @property string $body
 * @property bool $is_anonymous
 * @property ModerationStatus $moderation_status
 * @property Carbon|null $published_at
 * @property-read User $author
 * @property-read PublicPost $post
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['post_id', 'author_id', 'parent_id', 'body', 'is_anonymous'];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'moderation_status' => ModerationStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<PublicPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(PublicPost::class, 'post_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * @param  Builder<Comment>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('moderation_status', ModerationStatus::Approved);
    }
}
