<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid(),
            'public_key' => fake()->sha256(),
            'auth_token' => fake()->md5(),
            'device_token' => null,
            'device_name' => 'Chrome en '.fake()->word(),
            'last_used_at' => now(),
        ];
    }
}
