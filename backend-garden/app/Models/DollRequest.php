<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DollRequestStatus;
use App\Exceptions\ApiException;
use Database\Factories\DollRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * State transitions go through the guarded `mark*()`/`accept()`/`reject()`/…
 * methods (mirrors `LetterDelivery`, docs/api/dolls.md). Never
 * `->update(['status' => …])` from a controller.
 *
 * @property string $id
 * @property string|null $client_id
 * @property string|null $doll_id
 * @property DollRequestStatus $status
 * @property string $occasion
 * @property string|null $brief_notes
 * @property string|null $target_recipient_hint
 * @property list<string> $desired_tone
 * @property Carbon|null $deadline_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property int|null $client_rating
 * @property string|null $client_rating_comment
 * @property Carbon|null $rated_at
 * @property-read User|null $client
 * @property-read User|null $doll
 */
class DollRequest extends Model
{
    /** @use HasFactory<DollRequestFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'desired_tone' => '[]',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'occasion', 'brief_notes', 'target_recipient_hint', 'desired_tone', 'deadline_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DollRequestStatus::class,
            'desired_tone' => 'array',
            'deadline_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'client_rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doll(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doll_id');
    }

    /**
     * The chat transcript, oldest first.
     *
     * @return HasMany<DollChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DollChatMessage::class);
    }

    /**
     * Scoped route binding for `/drafts/{draft}` hangs off this. Deliberately
     * NOT filtered to `type = draft`: scoping by request is what enforces the
     * isolation, and letting a non-draft resolve lets the controller answer
     * `NOT_A_DRAFT` instead of a misleading 404.
     *
     * @return HasMany<DollChatMessage, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(DollChatMessage::class);
    }

    /**
     * @param  Builder<DollRequest>  $query
     */
    public function scopeForDoll(Builder $query, string $userId): void
    {
        $query->where('doll_id', $userId);
    }

    /**
     * @param  Builder<DollRequest>  $query
     */
    public function scopeForClient(Builder $query, string $userId): void
    {
        $query->where('client_id', $userId);
    }

    // --- State machine -------------------------------------------------------

    /** pending → accepted. Clears the 48h expiry — it no longer applies. */
    public function accept(): void
    {
        $this->assertStatus(DollRequestStatus::Pending);

        $this->forceFill([
            'status' => DollRequestStatus::Accepted,
            'accepted_at' => now(),
            'expires_at' => null,
        ])->save();
    }

    /** pending → rejected. */
    public function reject(): void
    {
        $this->assertStatus(DollRequestStatus::Pending);

        $this->forceFill([
            'status' => DollRequestStatus::Rejected,
            'rejected_at' => now(),
        ])->save();
    }

    /** accepted → in_progress. */
    public function start(): void
    {
        $this->assertStatus(DollRequestStatus::Accepted);

        $this->forceFill([
            'status' => DollRequestStatus::InProgress,
            'started_at' => now(),
        ])->save();
    }

    /** in_progress → awaiting_client. Set when the Doll needs the client's input (chat, Fase 3C). */
    public function awaitClient(): void
    {
        $this->assertStatus(DollRequestStatus::InProgress);

        $this->forceFill(['status' => DollRequestStatus::AwaitingClient])->save();
    }

    /** awaiting_client → in_progress. Set once the client responds (chat, Fase 3C). */
    public function resume(): void
    {
        $this->assertStatus(DollRequestStatus::AwaitingClient);

        $this->forceFill(['status' => DollRequestStatus::InProgress])->save();
    }

    /**
     * in_progress|awaiting_client → completed. Triggered by draft approval
     * (Fase 3C, `POST /doll-requests/{id}/drafts/{draftId}/approve`) — not a
     * standalone endpoint in Fase 3B.
     */
    public function complete(): void
    {
        $this->assertStatus(DollRequestStatus::InProgress, DollRequestStatus::AwaitingClient);

        $this->forceFill([
            'status' => DollRequestStatus::Completed,
            'completed_at' => now(),
        ])->save();
    }

    /** → cancelled, from any non-terminal state. Either party may call this. */
    public function cancel(): void
    {
        if ($this->status->isTerminal()) {
            throw new ApiException('La operación no aplica al estado actual de la solicitud.', 'INVALID_STATE_TRANSITION', 409);
        }

        $this->forceFill([
            'status' => DollRequestStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();
    }

    /** pending → expired. Called by ExpireStaleDollRequestsJob once `expires_at` has passed. */
    public function expire(): void
    {
        $this->assertStatus(DollRequestStatus::Pending);

        $this->forceFill([
            'status' => DollRequestStatus::Expired,
            'expires_at' => null,
        ])->save();
    }

    /**
     * The client rates a completed request once. Recalculating the Doll's
     * aggregate rating is a separate job (Valoraciones — RecalculateDollRatingsJob).
     */
    public function rate(int $rating, ?string $comment): void
    {
        $this->assertStatus(DollRequestStatus::Completed);

        if ($this->rated_at !== null) {
            throw new ApiException('Ya has valorado esta solicitud.', 'ALREADY_RATED', 409);
        }

        $this->forceFill([
            'client_rating' => $rating,
            'client_rating_comment' => $comment,
            'rated_at' => now(),
        ])->save();
    }

    private function assertStatus(DollRequestStatus ...$allowed): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw new ApiException(
                'La operación no aplica al estado actual de la solicitud.',
                'INVALID_STATE_TRANSITION',
                409,
            );
        }
    }
}
