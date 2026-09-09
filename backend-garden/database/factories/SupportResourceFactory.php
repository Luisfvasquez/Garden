<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportResource>
 */
class SupportResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_code' => strtoupper(fake()->countryCode()),
            'topic' => fake()->randomElement(['self_harm', 'grief', 'general']),
            'name' => fake()->company().' Helpline',
            'description' => fake()->sentence(),
            'phone' => fake()->numerify('### ### ###'),
            'sms' => null,
            'url' => fake()->url(),
            'hours' => fake()->randomElement(['24/7', '09:00–21:00']),
            'languages' => ['es'],
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function global(): self
    {
        return $this->state(fn (): array => ['country_code' => null]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
