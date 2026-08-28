<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'pen_name' => null,
            'postal_handle' => Str::slug((string) Str::of($name)->ascii()->limit(24, '')).'-'.bin2hex(random_bytes(2)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Client,
            'status' => UserStatus::Active,
            'country_code' => Str::upper(fake()->countryCode()),
            'timezone' => fake()->timezone(),
            'locale' => fake()->randomElement(['es', 'en']),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function doll(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Doll,
        ]);
    }

    public function withSettings(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserSettings::query()->firstOrCreate(['user_id' => $user->id]);
        });
    }
}
