<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeapDayPolicy;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleTriggerType;
use App\Enums\TransitTier;
use Database\Factories\LetterScheduleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Intent for a recurring / future letter. Stores local date + local time +
 * frozen timezone only; UTC instants are derived when occurrences materialise
 * (docs/api/programaciones.md, ADR-0002 and ADR-0009).
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $recipient_id
 * @property string $name
 * @property RecurrenceType $recurrence_type
 * @property Carbon $anchor_date
 * @property string $local_time
 * @property string $timezone
 * @property int|null $occurrences_total
 * @property list<string>|null $custom_dates
 * @property LeapDayPolicy $leap_day_policy
 * @property ScheduleTriggerType $trigger_type
 * @property string|null $letter_id
 * @property TransitTier $tier
 * @property bool $is_anonymous
 * @property ScheduleStatus $status
 * @property Carbon|null $paused_at
 * @property Carbon|null $last_materialized_at
 * @property-read User $user
 * @property-read User|null $recipient
 * @property-read Letter|null $letter
 * @property-read Collection<int, LetterScheduleOccurrence> $occurrences
 */
class LetterSchedule extends Model
{
    /** @use HasFactory<LetterScheduleFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'recurrence_type' => 'yearly',
        'leap_day_policy' => 'feb_28',
        'trigger_type' => 'date',
        'tier' => 'standard',
        'is_anonymous' => false,
        'status' => 'active',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recipient_id',
        'name',
        'recurrence_type',
        'anchor_date',
        'local_time',
        'timezone',
        'occurrences_total',
        'custom_dates',
        'leap_day_policy',
        'trigger_type',
        'letter_id',
        'tier',
        'is_anonymous',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurrence_type' => RecurrenceType::class,
            'anchor_date' => 'date',
            'occurrences_total' => 'integer',
            'custom_dates' => 'array',
            'leap_day_policy' => LeapDayPolicy::class,
            'trigger_type' => ScheduleTriggerType::class,
            'tier' => TransitTier::class,
            'is_anonymous' => 'boolean',
            'status' => ScheduleStatus::class,
            'paused_at' => 'datetime',
            'last_materialized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<Letter, $this>
     */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    /**
     * @return HasMany<LetterScheduleOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(LetterScheduleOccurrence::class, 'schedule_id');
    }

    public function pause(): void
    {
        if ($this->status !== ScheduleStatus::Active) {
            return;
        }

        $this->forceFill(['status' => ScheduleStatus::Paused, 'paused_at' => now()])->save();
    }

    public function resume(): void
    {
        if ($this->status !== ScheduleStatus::Paused) {
            return;
        }

        $this->forceFill(['status' => ScheduleStatus::Active, 'paused_at' => null])->save();
    }
}
