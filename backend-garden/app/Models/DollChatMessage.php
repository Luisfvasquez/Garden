<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DollChatMessageType;
use Database\Factories\DollChatMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a Doll chat. `body` is encrypted at rest, same as `letters.body`.
 *
 * @property string $id
 * @property string $doll_request_id
 * @property string|null $sender_id
 * @property DollChatMessageType $type
 * @property string|null $body
 * @property array<string, mixed>|null $draft_payload
 * @property int|null $draft_version
 * @property Carbon|null $draft_approved_at
 * @property string|null $attachment_path
 * @property list<string>|null $pii_flags
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DollRequest $dollRequest
 * @property-read User|null $sender
 */
class DollChatMessage extends Model
{
    /** @use HasFactory<DollChatMessageFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'text',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type', 'body', 'draft_payload', 'draft_version', 'attachment_path', 'pii_flags',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DollChatMessageType::class,
            'body' => 'encrypted',
            'draft_payload' => 'array',
            'draft_version' => 'integer',
            'pii_flags' => 'array',
            'draft_approved_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DollRequest, $this>
     */
    public function dollRequest(): BelongsTo
    {
        return $this->belongsTo(DollRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @param  Builder<DollChatMessage>  $query
     */
    public function scopeDrafts(Builder $query): void
    {
        $query->where('type', DollChatMessageType::Draft);
    }

    public function isDraft(): bool
    {
        return $this->type === DollChatMessageType::Draft;
    }

    /** A draft can only be approved once; the second call is a no-op for the caller to catch. */
    public function markDraftApproved(): void
    {
        $this->forceFill(['draft_approved_at' => now()])->save();
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
