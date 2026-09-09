<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\OccurrenceStatus;
use App\Models\LetterSchedule;
use App\Models\LetterScheduleOccurrence;

/**
 * The timeline for `GET /schedules/{id}/occurrences`: every planned occurrence,
 * virtual or materialised, merged into one ordered list the front can render
 * as-is (docs/api/programaciones.md, ADR-0002).
 */
class OccurrenceTimeline
{
    public function __construct(private readonly OccurrenceGenerator $generator) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, filled: int, delivered: int}}
     */
    public function build(LetterSchedule $schedule): array
    {
        /** @var array<string, LetterScheduleOccurrence> $rows */
        $rows = [];
        foreach ($schedule->occurrences()->with(['letter:id,title', 'delivery:id,status'])->get() as $occ) {
            $rows[$occ->occurrence_date->toDateString()] = $occ;
        }

        $data = [];
        $filled = 0;
        $delivered = 0;

        foreach ($this->generator->all($schedule) as $occurrence) {
            $row = $rows[$occurrence->date] ?? null;

            $letter = $row?->letter;
            if ($letter === null && $schedule->letter_id !== null) {
                $letter = $schedule->letter;
            }

            $delivery = $row?->delivery;

            $status = match (true) {
                $delivery !== null => OccurrenceStatus::fromDelivery($delivery->status),
                $letter !== null => OccurrenceStatus::Pending,
                default => OccurrenceStatus::Empty,
            };

            if ($status !== OccurrenceStatus::Empty) {
                $filled++;
            }
            if ($status === OccurrenceStatus::Delivered) {
                $delivered++;
            }

            $data[] = [
                'date' => $occurrence->date,
                'local_time' => $occurrence->localTime,
                'runs_at' => $occurrence->runsAt->toIso8601ZuluString(),
                'letter' => $letter === null ? null : [
                    'id' => $letter->id,
                    'title' => $letter->title,
                ],
                'delivery_id' => $delivery?->id,
                'status' => $status->value,
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => count($data),
                'filled' => $filled,
                'delivered' => $delivered,
            ],
        ];
    }
}
