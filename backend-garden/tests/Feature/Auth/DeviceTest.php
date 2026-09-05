<?php

declare(strict_types=1);

use App\Models\User;

it('lists the caller devices and flags the current one', function (): void {
    $user = User::factory()->create();
    $user->createToken('Laptop');
    $current = $user->createToken('Pixel 9');

    $response = $this->withToken($current->plainTextToken)
        ->getJson('/api/v1/auth/devices')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'device_name', 'last_used_at', 'created_at', 'current']]]);

    $byName = collect($response->json('data'))->keyBy('device_name');
    expect($byName['Pixel 9']['current'])->toBeTrue()
        ->and($byName['Laptop']['current'])->toBeFalse();
});

it('revokes one of the caller own devices', function (): void {
    $user = User::factory()->create();
    $victim = $user->createToken('Old tablet');
    $current = $user->createToken('Pixel 9');

    $this->withToken($current->plainTextToken)
        ->deleteJson("/api/v1/auth/devices/{$victim->accessToken->getKey()}")
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['Pixel 9']);
});

it('cannot revoke another user device and does not reveal it exists', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherToken = $other->createToken('Their phone');
    $mine = $me->createToken('My phone');

    $this->withToken($mine->plainTextToken)
        ->deleteJson("/api/v1/auth/devices/{$otherToken->accessToken->getKey()}")
        ->assertNotFound()
        ->assertJsonPath('error_code', 'NOT_FOUND');

    expect($other->tokens()->count())->toBe(1);
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/auth/devices')->assertUnauthorized();
});
