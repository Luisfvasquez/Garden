<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DollRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DollRequest>
 */
class DollRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'doll_id' => User::factory(),
            'occasion' => fake()->sentence(4),
            'brief_notes' => fake()->paragraph(),
            'desired_tone' => ['íntimo'],
            'expires_at' => now()->addHours(48),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => 'accepted', 'accepted_at' => now(), 'expires_at' => null]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => 'in_progress',
            'accepted_at' => now()->subHour(),
            'started_at' => now(),
            'expires_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'accepted_at' => now()->subHours(2),
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => 'pending', 'expires_at' => now()->subHour()]);
    }
}
