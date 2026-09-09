<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\LeapDayPolicy;
use App\Enums\RecurrenceType;
use App\Models\LetterSchedule;
use Carbon\CarbonImmutable;

/**
 * Turns a `letter_schedule` into its ordered list of occurrences, on the fly.
 * Pure date math, no persistence — the timeline view and the materialisation
 * job both read from here (ADR-0002, ADR-0009).
 *
 * The golden rule: never precompute UTC. Store local date + local time + frozen
 * zone, and convert only at the moment you need the instant — zone offsets and
 * DST rules change over a ten-year horizon (docs/api/programaciones.md).
 */
class OccurrenceGenerator
{
    /**
     * Every occurrence the schedule plans, in chronological order.
     *
     * @return list<Occurrence>
     */
    public function all(LetterSchedule $schedule): array
    {
        $dates = $schedule->recurrence_type === RecurrenceType::CustomDates
            ? $this->customDates($schedule)
            : $this->steppedDates($schedule);

        $occurrences = [];
        foreach ($dates as $index => $date) {
            $occurrences[] = new Occurrence(
                index: $index,
                date: $date,
                localTime: $schedule->local_time,
                runsAt: $this->toUtc($date, $schedule->local_time, $schedule->timezone),
            );
        }

        return $occurrences;
    }

    /**
     * Occurrences whose instant falls within [`from`, `from` + `withinDays`].
     * This is the set `GenerateUpcomingDeliveriesJob` materialises.
     *
     * @return list<Occurrence>
     */
    public function upcoming(LetterSchedule $schedule, CarbonImmutable $from, int $withinDays): array
    {
        $until = $from->addDays($withinDays);

        return array_values(array_filter(
            $this->all($schedule),
            static fn (Occurrence $o): bool => $o->runsAt->betweenIncluded($from, $until),
        ));
    }

    /**
     * @return list<string> local "Y-m-d", chronological
     */
    private function steppedDates(LetterSchedule $schedule): array
    {
        $anchor = CarbonImmutable::parse($schedule->anchor_date->toDateString());
        $count = $schedule->recurrence_type === RecurrenceType::Once
            ? 1
            : max(1, (int) $schedule->occurrences_total);

        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = $this->step($anchor, $schedule->recurrence_type, $schedule->leap_day_policy, $i)
                ->toDateString();
        }

        return $dates;
    }

    private function step(CarbonImmutable $anchor, RecurrenceType $type, LeapDayPolicy $leap, int $i): CarbonImmutable
    {
        return match ($type) {
            RecurrenceType::Once => $anchor,
            RecurrenceType::Weekly => $anchor->addWeeks($i),
            // Clamp to the last day of a shorter month (Jan 31 → Feb 28/29).
            RecurrenceType::Monthly => $anchor->addMonthsNoOverflow($i),
            // Feb 29 anchor in a common year: `feb_28` clamps back to the 28th,
            // `mar_01` lets the overflow carry it to March 1st. Identical to
            // no-overflow for every date that exists in the target month.
            RecurrenceType::Yearly => $leap === LeapDayPolicy::Mar01
                ? $anchor->addYears($i)
                : $anchor->addYearsNoOverflow($i),
            RecurrenceType::CustomDates => $anchor, // unreachable; handled separately
        };
    }

    /**
     * @return list<string> local "Y-m-d", de-duped and chronological
     */
    private function customDates(LetterSchedule $schedule): array
    {
        $dates = array_map(
            static fn (string $d): string => CarbonImmutable::parse($d)->toDateString(),
            $schedule->custom_dates ?? [],
        );

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    private function toUtc(string $date, string $localTime, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $timezone)
            ->setTimeFromTimeString($localTime)
            ->utc();
    }
}
