<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('deactivates the account and revokes tokens', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel 9');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/me/deactivate')
        ->assertNoContent();

    $fresh = $user->fresh();
    expect($fresh->status)->toBe(UserStatus::Deactivated)
        ->and($fresh->deactivated_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
});

it('schedules deletion 30 days out and returns the date', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/v1/me')->assertStatus(202);

    expect($response->json('data.deletes_at'))->toBeString();
    expect($user->fresh()->deletes_at)->not->toBeNull();
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/me/deactivate')->assertUnauthorized();
    $this->deleteJson('/api/v1/me')->assertUnauthorized();
});
