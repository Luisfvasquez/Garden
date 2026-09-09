<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LetterScheduleOccurrenceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A concrete occurrence of a schedule that has a letter assigned and/or a
 * materialised delivery. Absent from this table ⇒ the occurrence is virtual
 * and `empty` (ADR-0002).
 *
 * @property string $id
 * @property string $schedule_id
 * @property Carbon $occurrence_date
 * @property string|null $letter_id
 * @property string|null $delivery_id
 * @property Carbon|null $materialized_at
 * @property-read LetterSchedule $schedule
 * @property-read Letter|null $letter
 * @property-read LetterDelivery|null $delivery
 */
class LetterScheduleOccurrence extends Model
{
    /** @use HasFactory<LetterScheduleOccurrenceFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'schedule_id',
        'occurrence_date',
        'letter_id',
        'delivery_id',
        'materialized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date',
            'materialized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LetterSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LetterSchedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<Letter, $this>
     */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    /**
     * @return BelongsTo<LetterDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(LetterDelivery::class, 'delivery_id');
    }

    public function isMaterialised(): bool
    {
        return $this->delivery_id !== null;
    }
}
