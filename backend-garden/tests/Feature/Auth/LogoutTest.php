<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('revokes the current mobile token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Pixel 9');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('leaves other devices signed in', function (): void {
    $user = User::factory()->create();
    $keep = $user->createToken('Laptop');
    $current = $user->createToken('Pixel 9');

    $this->withToken($current->plainTextToken)->postJson('/api/v1/auth/logout');

    expect($user->tokens()->pluck('name')->all())->toBe(['Laptop']);
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized()
        ->assertJsonPath('error_code', 'UNAUTHENTICATED');
});

it('clears the session for a SPA caller', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
});
