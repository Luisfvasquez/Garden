<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeapDayPolicy;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Enums\ScheduleTriggerType;
use App\Enums\TransitTier;
use App\Models\LetterSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterSchedule>
 */
class LetterScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_id' => User::factory(),
            'name' => fake()->sentence(3),
            'recurrence_type' => RecurrenceType::Yearly,
            'anchor_date' => now()->addYear()->toDateString(),
            'local_time' => '09:00',
            'timezone' => 'Europe/Madrid',
            'occurrences_total' => 10,
            'custom_dates' => null,
            'leap_day_policy' => LeapDayPolicy::Feb28,
            'trigger_type' => ScheduleTriggerType::Date,
            'letter_id' => null,
            'tier' => TransitTier::Standard,
            'is_anonymous' => false,
            'status' => ScheduleStatus::Active,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (): array => ['status' => ScheduleStatus::Paused, 'paused_at' => now()]);
    }

    public function weekly(int $total = 6): static
    {
        return $this->state(fn (): array => ['recurrence_type' => RecurrenceType::Weekly, 'occurrences_total' => $total]);
    }

    public function customDates(string ...$dates): static
    {
        return $this->state(fn (): array => [
            'recurrence_type' => RecurrenceType::CustomDates,
            'custom_dates' => array_values($dates),
            'occurrences_total' => null,
        ]);
    }
}
