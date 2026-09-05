<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rotates the postal handle and records the timestamp', function (): void {
    $user = User::factory()->create();
    $old = $user->postal_handle;
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/me/postal-handle/rotate')->assertOk();

    expect($response->json('data.postal_handle'))->not->toBe($old);
    expect($user->fresh()->postal_handle_rotated_at)->not->toBeNull();
});

it('refuses a second rotation inside the 30 day window with a retry hint', function (): void {
    $user = User::factory()->create(['postal_handle_rotated_at' => now()->subDays(10)]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/postal-handle/rotate')
        ->assertStatus(429)
        ->assertJsonPath('error_code', 'HANDLE_ROTATION_TOO_SOON')
        ->assertJsonPath('meta.retry_at', fn ($v) => is_string($v) && str_ends_with($v, 'Z'));
});

it('allows rotation again once the window has passed', function (): void {
    $user = User::factory()->create(['postal_handle_rotated_at' => now()->subDays(31)]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/postal-handle/rotate')->assertOk();
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/me/postal-handle/rotate')->assertUnauthorized();
});
