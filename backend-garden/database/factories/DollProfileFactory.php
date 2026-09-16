<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\DollProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DollProfile>
 */
class DollProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => fake()->sentence(4),
            'bio' => fake()->paragraph(),
            'specialties' => fake()->randomElements(['amor', 'duelo', 'disculpa', 'celebración', 'negocios'], 2),
            'languages' => ['es'],
            'tone_tags' => ['íntimo'],
            'rate_type' => 'free',
            'is_available' => true,
            'max_concurrent_requests' => 3,
        ];
    }

    /**
     * A real Doll: verified, and the linked user carries the role — mirrors
     * what `DollProfile::markVerified()` does for real.
     */
    public function verified(): static
    {
        return $this->state(fn (): array => ['verified_at' => now()])
            ->afterCreating(function (DollProfile $profile): void {
                $profile->user()->update(['role' => UserRole::Doll]);
            });
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => ['is_available' => false]);
    }
}
