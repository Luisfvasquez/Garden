<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;

it('logs in with a session cookie and no body', function (): void {
    User::factory()->create([
        'email' => 'violet@example.com',
        'password' => 'Password!234',
    ]);

    // Sanctum only issues a stateful session to requests from the SPA origin.
    $this->withHeaders(['Origin' => 'http://localhost']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'violet@example.com',
        'password' => 'Password!234',
    ])->assertNoContent();

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'violet@example.com');
});

it('returns INVALID_CREDENTIALS for a wrong password', function (): void {
    User::factory()->create(['email' => 'violet@example.com', 'password' => 'Password!234']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'violet@example.com',
        'password' => 'wrong',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
});

it('returns INVALID_CREDENTIALS for an unknown email (no user enumeration)', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'ghost@example.com',
        'password' => 'whatever',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
});

it('blocks a suspended account', function (): void {
    User::factory()->create([
        'email' => 'violet@example.com',
        'password' => 'Password!234',
        'status' => UserStatus::Suspended,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'violet@example.com',
        'password' => 'Password!234',
    ])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
});

it('reactivates a self-deactivated account on login', function (): void {
    $user = User::factory()->create([
        'email' => 'violet@example.com',
        'password' => 'Password!234',
        'status' => UserStatus::Deactivated,
        'deactivated_at' => now()->subDay(),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'violet@example.com',
        'password' => 'Password!234',
    ])->assertNoContent();

    expect($user->fresh()->status)->toBe(UserStatus::Active)
        ->and($user->fresh()->deactivated_at)->toBeNull();
});

it('throttles auth attempts at 5 per minute per IP', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.co', 'password' => 'x']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'a@b.co', 'password' => 'x'])
        ->assertStatus(429)
        ->assertJsonPath('error_code', 'RATE_LIMITED')
        ->assertHeader('Retry-After');
});
