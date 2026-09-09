<?php

declare(strict_types=1);

use App\Models\PushSubscription;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('exposes the VAPID public key publicly, disabled when unset', function (): void {
    config(['webpush.vapid.public_key' => '']);

    $this->getJson('/api/v1/push/vapid-public-key')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.public_key', null);

    config(['webpush.vapid.public_key' => 'BExample']);
    $this->getJson('/api/v1/push/vapid-public-key')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.public_key', 'BExample');
});

it('registers a web subscription and refreshes it on repeat', function (): void {
    Sanctum::actingAs($user = User::factory()->create());

    $payload = [
        'platform' => 'web',
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'public_key' => 'pub',
        'auth_token' => 'auth',
        'device_name' => 'Chrome en Mac',
    ];

    $this->postJson('/api/v1/push-subscriptions', $payload)->assertCreated();
    $this->postJson('/api/v1/push-subscriptions', [...$payload, 'device_name' => 'Chrome renombrado'])->assertCreated();

    expect(PushSubscription::where('user_id', $user->id)->count())->toBe(1)
        ->and(PushSubscription::first()->device_name)->toBe('Chrome renombrado');
});

it('validates a web subscription needs an endpoint', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/push-subscriptions', ['platform' => 'web'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['endpoint', 'public_key', 'auth_token']);
});

it('deletes only my own subscription', function (): void {
    $mine = PushSubscription::factory()->create();
    $other = PushSubscription::factory()->create();

    Sanctum::actingAs($mine->user);
    $this->deleteJson("/api/v1/push-subscriptions/{$other->id}")->assertNoContent();
    expect(PushSubscription::find($other->id))->not->toBeNull();

    $this->deleteJson("/api/v1/push-subscriptions/{$mine->id}")->assertNoContent();
    expect(PushSubscription::find($mine->id))->toBeNull();
});
