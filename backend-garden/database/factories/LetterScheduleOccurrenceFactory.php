<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LetterSchedule;
use App\Models\LetterScheduleOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterScheduleOccurrence>
 */
class LetterScheduleOccurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => LetterSchedule::factory(),
            'occurrence_date' => now()->addMonths(2)->toDateString(),
            'letter_id' => null,
            'delivery_id' => null,
            'materialized_at' => null,
        ];
    }
}
