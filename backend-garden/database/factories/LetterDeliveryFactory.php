<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryMode;
use App\Enums\DeliveryStatus;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterDelivery>
 */
class LetterDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $letter = Letter::factory();

        return [
            'letter_id' => $letter,
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'status' => DeliveryStatus::Queued,
            'delivery_mode' => DeliveryMode::Direct,
            'scheduled_for' => now(),
            'is_anonymous' => false,
            'allow_read_receipt' => true,
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliveryStatus::InTransit,
            'dispatched_at' => now()->subMinutes(30),
            'transit_duration_minutes' => 180,
            'delivered_at' => now()->addHours(2),
            'estimated_delivery_at' => now()->addHours(2)->addMinutes(14),
            'attempts' => 1,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeliveryStatus::Delivered,
            'dispatched_at' => now()->subHours(3),
            'transit_duration_minutes' => 180,
            'delivered_at' => now()->subMinutes(5),
            'estimated_delivery_at' => now()->subMinutes(5),
            'attempts' => 1,
        ]);
    }

    public function read(): static
    {
        return $this->delivered()->state(fn (array $attributes) => [
            'status' => DeliveryStatus::Read,
            'read_at' => now(),
        ]);
    }
}
