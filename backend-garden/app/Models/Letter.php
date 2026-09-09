<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LetterKind;
use App\Enums\ModerationStatus;
use Database\Factories\LetterFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Content and aesthetics only — no recipient, no status (ADR-0001).
 * `is_locked` flips true once a delivery has been dispatched; after that the
 * body is immutable for the recipient.
 *
 * @property string $id
 * @property string $author_id
 * @property string|null $title
 * @property array<string, mixed> $body
 * @property string|null $body_plain
 * @property int $word_count
 * @property array<string, mixed> $style
 * @property LetterKind $kind
 * @property bool $is_locked
 * @property string|null $doll_request_id
 * @property string|null $in_reply_to_delivery_id
 * @property ModerationStatus $moderation_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $author
 * @property-read Collection<int, LetterAttachment> $attachments
 * @property-read Collection<int, LetterDelivery> $deliveries
 */
class Letter extends Model
{
    /** @use HasFactory<LetterFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    private const WORDS_PER_MINUTE = 200;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'direct',
        'moderation_status' => 'approved',
        'is_locked' => false,
        'word_count' => 0,
        'style' => '{}',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'body_plain',
        'word_count',
        'style',
        'kind',
        'in_reply_to_delivery_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted:array',
            'style' => 'array',
            'kind' => LetterKind::class,
            'moderation_status' => ModerationStatus::class,
            'is_locked' => 'boolean',
            'word_count' => 'integer',
        ];
    }

    public function readingTimeMinutes(): int
    {
        return max(1, (int) ceil($this->word_count / self::WORDS_PER_MINUTE));
    }

    public function lock(): void
    {
        if (! $this->is_locked) {
            $this->forceFill(['is_locked' => true])->save();
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<LetterAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LetterAttachment::class);
    }

    /**
     * @return HasMany<LetterDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(LetterDelivery::class);
    }

    /**
     * @return BelongsTo<LetterDelivery, $this>
     */
    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(LetterDelivery::class, 'in_reply_to_delivery_id');
    }
}
