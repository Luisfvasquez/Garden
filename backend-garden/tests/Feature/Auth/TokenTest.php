<?php

declare(strict_types=1);

use App\Models\User;

it('issues a mobile token with an expiry', function (): void {
    User::factory()->create(['email' => 'violet@example.com', 'password' => 'Password!234']);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => 'violet@example.com',
        'password' => 'Password!234',
        'device_name' => 'Pixel 9',
    ])->assertCreated();

    expect($response->json('data.token'))->toBeString()->toContain('|')
        ->and($response->json('data.expires_at'))->toMatch('/Z$/');
});

it('names the token after the device so it shows up under /auth/devices', function (): void {
    $user = User::factory()->create(['email' => 'violet@example.com', 'password' => 'Password!234']);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'violet@example.com',
        'password' => 'Password!234',
        'device_name' => 'Pixel 9',
    ]);

    expect($user->tokens()->pluck('name')->all())->toBe(['Pixel 9']);
});

it('rejects bad credentials', function (): void {
    $this->postJson('/api/v1/auth/token', [
        'email' => 'violet@example.com',
        'password' => 'nope',
        'device_name' => 'Pixel 9',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
});

it('requires a device name', function (): void {
    User::factory()->create(['email' => 'violet@example.com', 'password' => 'Password!234']);

    $this->postJson('/api/v1/auth/token', [
        'email' => 'violet@example.com',
        'password' => 'Password!234',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_name');
});
