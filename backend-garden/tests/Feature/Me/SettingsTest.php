<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSettings;
use Laravel\Sanctum\Sanctum;

it('returns default settings, creating the row on first read', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/settings')
        ->assertOk()
        ->assertJsonPath('data.notify_email', true)
        ->assertJsonPath('data.notify_on_dispatch_confirm', false)
        ->assertJsonPath('data.theme', 'system')
        ->assertJsonPath('data.accepts_random_letters', false)
        ->assertJsonPath('data.random_letters_daily_cap', 3);

    expect(UserSettings::whereKey($user->id)->exists())->toBeTrue();
});

it('writes both user_settings columns and the user-level random-letter fields', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/settings', [
        'notify_email' => false,
        'quiet_hours_start' => '23:00',
        'quiet_hours_end' => '07:30',
        'theme' => 'dark',
        'accepts_random_letters' => true,
        'random_letters_daily_cap' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.notify_email', false)
        ->assertJsonPath('data.quiet_hours_start', '23:00')
        ->assertJsonPath('data.theme', 'dark')
        ->assertJsonPath('data.accepts_random_letters', true)
        ->assertJsonPath('data.random_letters_daily_cap', 5);

    expect($user->fresh()->accepts_random_letters)->toBeTrue()
        ->and($user->fresh()->random_letters_daily_cap)->toBe(5);
});

it('validates the daily cap ceiling', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me/settings', ['random_letters_daily_cap' => 99])
        ->assertStatus(422)
        ->assertJsonValidationErrors('random_letters_daily_cap');
});

it('rejects quiet hours given only half a range', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me/settings', ['quiet_hours_start' => '23:00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quiet_hours_end');
});

it('keeps each user settings isolated', function (): void {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Sanctum::actingAs($b);
    $this->patchJson('/api/v1/me/settings', ['notify_email' => false])->assertOk();

    UserSettings::firstOrCreate(['user_id' => $a->id]);
    expect(UserSettings::whereKey($a->id)->first()->notify_email)->toBeTrue();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me/settings')->assertUnauthorized();
});
