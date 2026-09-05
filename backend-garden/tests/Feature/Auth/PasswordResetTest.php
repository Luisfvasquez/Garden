<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('always answers 202 to forgot-password, even for an unknown address', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])
        ->assertStatus(202);

    Notification::assertNothingSent();
});

it('emails a reset link to a known address', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'violet@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'violet@example.com'])
        ->assertStatus(202);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password and revokes existing tokens', function (): void {
    $user = User::factory()->create(['email' => 'violet@example.com']);
    $user->createToken('old device');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'violet@example.com',
        'password' => 'BrandNew!234',
        'password_confirmation' => 'BrandNew!234',
    ])->assertNoContent();

    expect(Hash::check('BrandNew!234', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

it('rejects an invalid reset token with INVALID_RESET_TOKEN', function (): void {
    User::factory()->create(['email' => 'violet@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'violet@example.com',
        'password' => 'BrandNew!234',
        'password_confirmation' => 'BrandNew!234',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_RESET_TOKEN');
});
