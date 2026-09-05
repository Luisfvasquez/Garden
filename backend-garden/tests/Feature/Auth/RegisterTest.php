<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function validRegistration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Violet Evergarden',
        'email' => 'violet@example.com',
        'password' => 'Password!234',
        'password_confirmation' => 'Password!234',
        'birth_date' => now()->subYears(20)->toDateString(),
        'timezone' => 'America/New_York',
        'locale' => 'es',
        'accepts_terms' => true,
    ], $overrides);
}

it('registers a user, seeds settings and sends verification without a session', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', validRegistration());

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'violet@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.accepts_random_letters', false)
        ->assertJsonPath('data.role', 'client')
        ->assertJsonMissingPath('data.password');

    expect($response->json('data.postal_handle'))->toMatch('/^violet-evergarden-[0-9a-f]{4}$/');

    $user = User::firstWhere('email', 'violet@example.com');
    expect(UserSettings::whereKey($user->id)->exists())->toBeTrue();
    expect($this->app['auth']->guard('web')->check())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a registration under the minimum age with UNDER_MINIMUM_AGE', function (): void {
    $this->postJson('/api/v1/auth/register', validRegistration([
        'birth_date' => now()->subYears(15)->toDateString(),
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'UNDER_MINIMUM_AGE');
});

it('validates the payload', function (array $payload, string $field): void {
    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors($field);
})->with([
    'missing name' => [fn () => validRegistration(['name' => '']), 'name'],
    'bad email' => [fn () => validRegistration(['email' => 'nope']), 'email'],
    'unconfirmed password' => [fn () => validRegistration(['password_confirmation' => 'x']), 'password'],
    'bad timezone' => [fn () => validRegistration(['timezone' => 'Mars/Olympus']), 'timezone'],
    'unsupported locale' => [fn () => validRegistration(['locale' => 'fr']), 'locale'],
    'terms not accepted' => [fn () => validRegistration(['accepts_terms' => false]), 'accepts_terms'],
]);

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'violet@example.com']);

    $this->postJson('/api/v1/auth/register', validRegistration())
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});
