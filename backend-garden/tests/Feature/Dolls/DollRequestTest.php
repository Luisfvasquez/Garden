<?php

declare(strict_types=1);

use App\Models\DollProfile;
use App\Models\DollRequest;
use App\Models\FeatureFlag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => true]);
});

function dollRequestPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'occasion' => 'Disculpa a un hermano',
        'brief_notes' => 'Llevamos tres años sin hablar...',
        'target_recipient_hint' => 'Mi hermano mayor',
        'desired_tone' => ['íntimo', 'sobrio'],
    ], $overrides);
}

it('creates a request against a verified, available doll', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $profile = DollProfile::factory()->verified()->create();
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload(['doll_handle' => $profile->user->postal_handle]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.doll.postal_handle', $profile->user->postal_handle);

    $request = DollRequest::first();
    expect($request->client_id)->toBe($client->id)
        ->and($request->doll_id)->toBe($profile->user_id)
        ->and($request->expires_at)->not->toBeNull();
});

it('404s a request against a handle that is not a verified doll', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $stranger = User::factory()->create();
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload(['doll_handle' => $stranger->postal_handle]))
        ->assertNotFound();
});

it('refuses when the doll is unavailable', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $profile = DollProfile::factory()->verified()->unavailable()->create();
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload(['doll_handle' => $profile->user->postal_handle]))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'DOLL_UNAVAILABLE');
});

it('refuses when the doll is at capacity', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $profile = DollProfile::factory()->verified()->create(['max_concurrent_requests' => 1]);
    DollRequest::factory()->accepted()->create(['doll_id' => $profile->user_id]);
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload(['doll_handle' => $profile->user->postal_handle]))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'DOLL_UNAVAILABLE');
});

it('does not let pending requests alone exhaust capacity', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $profile = DollProfile::factory()->verified()->create(['max_concurrent_requests' => 1]);
    DollRequest::factory()->create(['doll_id' => $profile->user_id]); // pending, doesn't count
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload(['doll_handle' => $profile->user->postal_handle]))
        ->assertCreated();
});

it('rejects a target_recipient_hint that leaks contact info', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $profile = DollProfile::factory()->verified()->create();
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload([
        'doll_handle' => $profile->user->postal_handle,
        'target_recipient_hint' => 'Escríbele a hermano@example.com',
    ]))->assertStatus(422)->assertJsonPath('error_code', 'PII_DETECTED');
});

it('needs a verified email to create a request', function (): void {
    $client = User::factory()->unverified()->create();
    $profile = DollProfile::factory()->verified()->create();
    Sanctum::actingAs($client);

    $this->postJson('/api/v1/doll-requests', dollRequestPayload(['doll_handle' => $profile->user->postal_handle]))
        ->assertStatus(403);
});

it('shows a request to either party but 404s a stranger', function (): void {
    $request = DollRequest::factory()->create();

    Sanctum::actingAs($request->client);
    $this->getJson("/api/v1/doll-requests/{$request->id}")->assertOk();

    Sanctum::actingAs($request->doll);
    $this->getJson("/api/v1/doll-requests/{$request->id}")->assertOk();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/doll-requests/{$request->id}")->assertNotFound();
});

it('lists my requests filtered by role', function (): void {
    $me = User::factory()->create();
    DollRequest::factory()->create(['client_id' => $me->id]);
    DollRequest::factory()->create(['doll_id' => $me->id]);
    DollRequest::factory()->create(); // not mine
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/doll-requests')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/doll-requests?role=client')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/doll-requests?role=doll')->assertOk()->assertJsonCount(1, 'data');
});

it('lets the doll accept, and only the doll', function (): void {
    $request = DollRequest::factory()->create();

    Sanctum::actingAs($request->client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/accept")->assertNotFound();

    Sanctum::actingAs($request->doll);
    $this->postJson("/api/v1/doll-requests/{$request->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($request->fresh()->expires_at)->toBeNull();
});

it('lets the doll reject a pending request', function (): void {
    $request = DollRequest::factory()->create();
    Sanctum::actingAs($request->doll);

    $this->postJson("/api/v1/doll-requests/{$request->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

it('refuses to accept a request that is not pending', function (): void {
    $request = DollRequest::factory()->accepted()->create();
    Sanctum::actingAs($request->doll);

    $this->postJson("/api/v1/doll-requests/{$request->id}/accept")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('lets the doll start work once accepted', function (): void {
    $request = DollRequest::factory()->accepted()->create();
    Sanctum::actingAs($request->doll);

    $this->postJson("/api/v1/doll-requests/{$request->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
});

it('lets either party cancel a non-terminal request', function (): void {
    $request = DollRequest::factory()->inProgress()->create();

    Sanctum::actingAs($request->client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('refuses to cancel a completed request', function (): void {
    $request = DollRequest::factory()->completed()->create();
    Sanctum::actingAs($request->client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('never exposes a stranger to the request', function (): void {
    $request = DollRequest::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/doll-requests/{$request->id}/cancel")->assertNotFound();
});
