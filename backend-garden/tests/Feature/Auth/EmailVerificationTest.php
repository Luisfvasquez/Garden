<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => $hash ?? sha1($user->email)],
        absolute: false,
    );
}

it('verifies the email from a signed link', function (): void {
    $user = User::factory()->unverified()->create();

    $this->postJson(verificationUrl($user))->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered hash with INVALID_VERIFICATION_LINK', function (): void {
    $user = User::factory()->unverified()->create();

    $this->postJson(verificationUrl($user, hash: sha1('someone-else@example.com')))
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'INVALID_VERIFICATION_LINK');
});

it('rejects an unsigned or expired link', function (): void {
    $user = User::factory()->unverified()->create();

    $this->postJson("/api/v1/auth/email/verify/{$user->id}/".sha1($user->email))
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'INVALID_VERIFICATION_LINK');
});

it('is a 409 when the email is already verified', function (): void {
    $user = User::factory()->create(); // verified by default

    $this->postJson(verificationUrl($user))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'EMAIL_ALREADY_VERIFIED');
});

it('resends the verification email to the current user', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/email/resend')->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('will not resend once verified', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/email/resend')
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'EMAIL_ALREADY_VERIFIED');
});
