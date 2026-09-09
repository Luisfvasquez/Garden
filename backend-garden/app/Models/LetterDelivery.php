<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryMode;
use App\Enums\DeliveryStatus;
use App\Enums\TransitTier;
use App\Exceptions\ApiException;
use Database\Factories\LetterDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One row = one letter delivered to one person on one date (ADR-0001).
 * State transitions go through the `mark*()` / `cancel()` methods, which
 * validate the origin status, are atomic, and append to `delivery_events`
 * (../CLAUDE.md, spec §7.1). Never `->update(['status' => …])` from a controller.
 *
 * @property string $id
 * @property string $letter_id
 * @property string|null $sender_id
 * @property string|null $recipient_id
 * @property string|null $recipient_email
 * @property string|null $schedule_id
 * @property DeliveryStatus $status
 * @property DeliveryMode $delivery_mode
 * @property TransitTier $tier
 * @property Carbon $scheduled_for
 * @property Carbon|null $dispatched_at
 * @property int|null $transit_duration_minutes
 * @property Carbon|null $estimated_delivery_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $archived_at
 * @property string|null $failure_reason
 * @property Carbon|null $held_at
 * @property string|null $anonymous_reply_delivery_id
 * @property Carbon|null $open_correspondence_sender_at
 * @property Carbon|null $open_correspondence_recipient_at
 * @property Carbon|null $correspondence_opened_at
 * @property int $attempts
 * @property bool $is_anonymous
 * @property bool $is_favorite
 * @property bool $allow_read_receipt
 * @property Carbon|null $reveal_sender_at
 * @property string|null $dispatch_batch_id
 * @property-read Letter $letter
 * @property-read User|null $sender
 * @property-read User|null $recipient
 * @property-read Collection<int, DeliveryEvent> $events
 */
class LetterDelivery extends Model
{
    /** @use HasFactory<LetterDeliveryFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'delivery_mode' => 'direct',
        'tier' => 'standard',
        'attempts' => 0,
        'is_anonymous' => false,
        'is_favorite' => false,
        'allow_read_receipt' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'letter_id',
        'sender_id',
        'recipient_id',
        'recipient_email',
        'schedule_id',
        'delivery_mode',
        'tier',
        'scheduled_for',
        'is_anonymous',
        'allow_read_receipt',
        'reveal_sender_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivery_mode' => DeliveryMode::class,
            'tier' => TransitTier::class,
            'scheduled_for' => 'datetime',
            'dispatched_at' => 'datetime',
            'transit_duration_minutes' => 'integer',
            'estimated_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'attempts' => 'integer',
            'is_anonymous' => 'boolean',
            'is_favorite' => 'boolean',
            'allow_read_receipt' => 'boolean',
            'reveal_sender_at' => 'datetime',
            'held_at' => 'datetime',
            'open_correspondence_sender_at' => 'datetime',
            'open_correspondence_recipient_at' => 'datetime',
            'correspondence_opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Letter, $this>
     */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return HasMany<DeliveryEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)->orderBy('occurred_at');
    }

    public function isOpened(): bool
    {
        return $this->read_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    // --- Scopes ----------------------------------------------------------------

    /**
     * @param  Builder<LetterDelivery>  $query
     */
    public function scopeForSender(Builder $query, string $userId): void
    {
        $query->where('sender_id', $userId);
    }

    /**
     * Everything visible in a recipient's mailbox: delivered or read only.
     * `in_transit`, `queued`, `failed`, `cancelled` and `blocked` never appear.
     *
     * @param  Builder<LetterDelivery>  $query
     */
    public function scopeInMailbox(Builder $query, string $userId): void
    {
        $query->where('recipient_id', $userId)
            ->whereIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Read]);
    }

    // --- State machine -----------------------------------------------------

    /**
     * queued → in_transit. Called by DispatchSingleLetterJob once transit is
     * calculated. `$deliverAt` is the target arrival; `$estimateAt` is the
     * fuzzy figure shown to the sender.
     */
    public function markInTransit(int $transitMinutes, Carbon $deliverAt, Carbon $estimateAt): void
    {
        $this->assertStatus(DeliveryStatus::Queued);

        DB::transaction(function () use ($transitMinutes, $deliverAt, $estimateAt): void {
            $this->forceFill([
                'status' => DeliveryStatus::InTransit,
                'dispatched_at' => now(),
                'transit_duration_minutes' => $transitMinutes,
                'delivered_at' => $deliverAt,
                'estimated_delivery_at' => $estimateAt,
                'attempts' => $this->attempts + 1,
            ])->save();

            $this->recordEvent(DeliveryEventType::Dispatched);
            $this->recordEvent(DeliveryEventType::InTransit);
        });
    }

    /** in_transit → delivered. */
    public function markDelivered(): void
    {
        $this->assertStatus(DeliveryStatus::InTransit);

        DB::transaction(function (): void {
            $this->forceFill([
                'status' => DeliveryStatus::Delivered,
                'delivered_at' => now(),
            ])->save();

            $this->recordEvent(DeliveryEventType::Delivered);
        });
    }

    /** delivered → read. Idempotent: a second open is a no-op. */
    public function markRead(): void
    {
        if ($this->status === DeliveryStatus::Read) {
            return;
        }

        $this->assertStatus(DeliveryStatus::Delivered);

        DB::transaction(function (): void {
            $this->forceFill([
                'status' => DeliveryStatus::Read,
                'read_at' => now(),
            ])->save();

            $this->recordEvent(DeliveryEventType::Read);
        });
    }

    /** queued|in_transit → failed (recipient gone, suspended, …). */
    public function markFailed(string $reason): void
    {
        $this->assertStatus(DeliveryStatus::Queued, DeliveryStatus::InTransit);

        DB::transaction(function () use ($reason): void {
            $this->forceFill([
                'status' => DeliveryStatus::Failed,
                'failure_reason' => $reason,
            ])->save();

            $this->recordEvent(DeliveryEventType::Failed, ['reason' => $reason]);
        });
    }

    /**
     * queued|in_transit → blocked. Silent: the sender's view still reads
     * "delivered" and the tracking timeline mirrors a normal delivery
     * (spec §7.1, ADR-0007).
     */
    public function markBlocked(): void
    {
        $this->assertStatus(DeliveryStatus::Queued, DeliveryStatus::InTransit);

        DB::transaction(function (): void {
            $this->forceFill([
                'status' => DeliveryStatus::Blocked,
                'delivered_at' => now(),
            ])->save();

            $this->recordEvent(DeliveryEventType::Delivered, ['outcome' => 'blocked']);
        });
    }

    /**
     * → cancelled. Always from `queued`; from `in_transit` only inside the
     * grace window since dispatch, otherwise GRACE_PERIOD_EXPIRED.
     */
    public function cancel(): void
    {
        if (! $this->canCancel()) {
            throw $this->status === DeliveryStatus::InTransit
                ? new ApiException('Ya no se puede cancelar la entrega.', 'GRACE_PERIOD_EXPIRED', 409)
                : new ApiException('La operación no aplica al estado actual.', 'INVALID_STATE_TRANSITION', 409);
        }

        DB::transaction(function (): void {
            $this->forceFill([
                'status' => DeliveryStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->recordEvent(DeliveryEventType::Cancelled);
        });
    }

    public function canCancel(): bool
    {
        return match ($this->status) {
            DeliveryStatus::Queued => true,
            DeliveryStatus::InTransit => $this->dispatched_at !== null
                && $this->dispatched_at->diffInMinutes(now()) < (int) config('postal.grace_period_minutes'),
            default => false,
        };
    }

    // --- Mailbox flags (not lifecycle transitions) ----------------------------

    public function setArchived(bool $archived): void
    {
        $this->forceFill(['archived_at' => $archived ? now() : null])->save();
    }

    public function setFavorite(bool $favorite): void
    {
        $this->forceFill(['is_favorite' => $favorite])->save();
    }

    // --- Bottle at sea (docs/api/botella-al-mar.md) ------------------------

    /** Records the single anonymous reply the recipient is allowed. */
    public function linkAnonymousReply(string $replyDeliveryId): void
    {
        $this->forceFill(['anonymous_reply_delivery_id' => $replyDeliveryId])->save();
    }

    public function hasAnonymousReply(): bool
    {
        return $this->anonymous_reply_delivery_id !== null;
    }

    /**
     * One party opts in to open correspondence. Handles are revealed only once
     * BOTH sides have.
     */
    public function acceptOpenCorrespondence(string $userId): void
    {
        $column = match ($userId) {
            $this->sender_id => 'open_correspondence_sender_at',
            $this->recipient_id => 'open_correspondence_recipient_at',
            default => null,
        };

        if ($column === null || $this->{$column} !== null) {
            return;
        }

        $this->forceFill([$column => now()])->save();

        if ($this->open_correspondence_sender_at !== null
            && $this->open_correspondence_recipient_at !== null
            && $this->correspondence_opened_at === null) {
            $this->forceFill(['correspondence_opened_at' => now()])->save();
        }
    }

    public function correspondenceOpened(): bool
    {
        return $this->correspondence_opened_at !== null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordEvent(DeliveryEventType $event, array $metadata = [], ?Carbon $at = null): DeliveryEvent
    {
        return $this->events()->create([
            'event' => $event,
            'occurred_at' => $at ?? now(),
            'metadata' => $metadata,
        ]);
    }

    private function assertStatus(DeliveryStatus ...$allowed): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw new ApiException(
                'La operación no aplica al estado actual de la entrega.',
                'INVALID_STATE_TRANSITION',
                409,
            );
        }
    }
}
