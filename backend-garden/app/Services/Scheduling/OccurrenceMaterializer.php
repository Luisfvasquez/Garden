<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryMode;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\LetterSchedule;
use App\Models\LetterScheduleOccurrence;
use App\Services\Postal\TransitCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns one planned occurrence into a real `letter_delivery`. Idempotent: an
 * occurrence that already has a delivery is left untouched
 * (backend-garden/docs/jobs-y-colas.md).
 */
class OccurrenceMaterializer
{
    public function __construct(private readonly TransitCalculator $calculator) {}

    /**
     * @return LetterScheduleOccurrence|null the occurrence row, or null when it
     *                                       has no letter to send (stays virtual)
     */
    public function materialise(LetterSchedule $schedule, Occurrence $occurrence): ?LetterScheduleOccurrence
    {
        $recipient = $schedule->recipient;
        if ($recipient === null || ! $recipient->status->canAuthenticate()) {
            return null;
        }

        return DB::transaction(function () use ($schedule, $occurrence, $recipient): ?LetterScheduleOccurrence {
            /** @var LetterScheduleOccurrence $occ */
            $occ = LetterScheduleOccurrence::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'schedule_id' => $schedule->getKey(),
                    'occurrence_date' => $occurrence->date,
                ]);

            if ($occ->delivery_id !== null) {
                return $occ; // already materialised
            }

            $letterId = $occ->letter_id ?? $schedule->letter_id;
            if ($letterId === null) {
                return null; // empty occurrence
            }

            $letter = Letter::find($letterId);
            if ($letter === null) {
                return null;
            }

            $crossBorder = $schedule->user->country_code !== null
                && $recipient->country_code !== null
                && $schedule->user->country_code !== $recipient->country_code;

            $arriveAt = Carbon::instance($occurrence->runsAt->toDateTime());
            $scheduledFor = $this->calculator->scheduledFor($arriveAt, $schedule->tier, $crossBorder);

            $delivery = new LetterDelivery([
                'schedule_id' => $schedule->getKey(),
                'sender_id' => $schedule->user_id,
                'recipient_id' => $recipient->getKey(),
                'delivery_mode' => DeliveryMode::Direct,
                'tier' => $schedule->tier,
                'scheduled_for' => $scheduledFor,
                'is_anonymous' => $schedule->is_anonymous,
            ]);
            $delivery->letter()->associate($letter);
            $delivery->estimated_delivery_at = $this->calculator
                ->provisionalEstimate($scheduledFor, $schedule->tier, $crossBorder);
            $delivery->save();

            $delivery->recordEvent(DeliveryEventType::Created);
            $delivery->recordEvent(DeliveryEventType::Queued);

            $letter->lock();

            $occ->fill([
                'letter_id' => $letterId,
                'delivery_id' => $delivery->getKey(),
                'materialized_at' => now(),
            ])->save();

            return $occ;
        });
    }
}
