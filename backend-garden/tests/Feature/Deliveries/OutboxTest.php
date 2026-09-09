<?php

declare(strict_types=1);

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Jobs\Postal\DispatchSingleLetterJob;
use App\Models\LetterDelivery;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lists the caller outbox with a cursor envelope', function (): void {
    $me = User::factory()->create();
    LetterDelivery::factory()->count(3)->create(['sender_id' => $me->id]);
    LetterDelivery::factory()->count(2)->create(); // someone else's

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/deliveries')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'status', 'can_cancel', 'recipient']], 'meta' => ['next_cursor']]);
});

it('treats ?status=delivered as covering blocked (the sender never learns of a block)', function (): void {
    $me = User::factory()->create();
    LetterDelivery::factory()->delivered()->create(['sender_id' => $me->id]);
    $blocked = LetterDelivery::factory()->inTransit()->create(['sender_id' => $me->id]);
    $blocked->markBlocked();

    Sanctum::actingAs($me);

    $response = $this->getJson('/api/v1/deliveries?status=delivered')->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    foreach ($response->json('data') as $row) {
        expect($row['status'])->toBe('delivered');
    }
});

it('renders the tracking timeline with labels and only leaks the office', function (): void {
    $delivery = LetterDelivery::factory()->create(['scheduled_for' => now()->subMinute()]);
    DispatchSingleLetterJob::dispatchSync($delivery->id);
    $delivery->markBlocked(); // records a Delivered event with a hidden {outcome: blocked}

    Sanctum::actingAs($delivery->sender);

    $response = $this->getJson("/api/v1/deliveries/{$delivery->id}/tracking")->assertOk();

    $events = collect($response->json('data'));
    expect($events->pluck('event'))->toContain('dispatched', 'sorting_office', 'delivered');

    $office = $events->firstWhere('event', 'sorting_office');
    expect($office['label'])->toStartWith('En la oficina de ')
        ->and($office['metadata'])->toHaveKey('office');

    // The block must never surface anywhere in the timeline.
    expect(json_encode($response->json()))->not->toContain('blocked')->not->toContain('outcome');
});

it('cancels a queued delivery', function (): void {
    $delivery = LetterDelivery::factory()->create();
    Sanctum::actingAs($delivery->sender);

    $this->postJson("/api/v1/deliveries/{$delivery->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($delivery->fresh()->events()->pluck('event')->all())->toContain(DeliveryEventType::Cancelled);
});

it('refuses to cancel once the grace window has closed', function (): void {
    config(['postal.grace_period_minutes' => 15]);
    $delivery = LetterDelivery::factory()->inTransit()->create(['dispatched_at' => now()->subMinutes(40)]);
    Sanctum::actingAs($delivery->sender);

    $this->postJson("/api/v1/deliveries/{$delivery->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'GRACE_PERIOD_EXPIRED');

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::InTransit);
});

it('refuses to cancel a delivered letter', function (): void {
    $delivery = LetterDelivery::factory()->delivered()->create();
    Sanctum::actingAs($delivery->sender);

    $this->postJson("/api/v1/deliveries/{$delivery->id}/cancel")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('404s outbox/tracking/cancel for a delivery you did not send', function (): void {
    $delivery = LetterDelivery::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/deliveries/{$delivery->id}")->assertNotFound();
    $this->getJson("/api/v1/deliveries/{$delivery->id}/tracking")->assertNotFound();
    $this->postJson("/api/v1/deliveries/{$delivery->id}/cancel")->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/deliveries')->assertUnauthorized();
});
