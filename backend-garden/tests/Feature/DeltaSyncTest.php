<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Delta sync (`?updated_since=`) for the mobile client — spec §12.4,
 * docs/api/_convenciones.md.
 */
function deliveredTo(User $recipient, ?User $sender = null): LetterDelivery
{
    $sender ??= User::factory()->create();
    $letter = Letter::factory()->create(['author_id' => $sender->id]);

    return LetterDelivery::factory()->create([
        'letter_id' => $letter->id,
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'status' => DeliveryStatus::Delivered,
        'delivered_at' => now(),
    ]);
}

// --- The watermark -----------------------------------------------------------

it('returns a server-issued watermark even on a full sync', function (): void {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $response = $this->getJson('/api/v1/mailbox')->assertOk();

    expect($response->json('meta.synced_at'))->toBeString()
        ->and($response->json('meta.is_delta'))->toBeFalse();
});

it('takes the watermark before the query, so a concurrent write is re-sent, not lost', function (): void {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $before = now()->subMinute();
    $response = $this->getJson('/api/v1/mailbox?updated_since='.$before->toIso8601ZuluString())->assertOk();

    // The watermark must not run ahead of the server's own clock at request
    // time; a later one would create a window in which writes are never seen.
    expect(Carbon\Carbon::parse($response->json('meta.synced_at')))
        ->toBeLessThanOrEqual(now()->addSecond());
});

it('round-trips: a second sync with the returned watermark is empty', function (): void {
    $me = User::factory()->create();
    deliveredTo($me);
    Sanctum::actingAs($me);

    $first = $this->getJson('/api/v1/mailbox')->assertOk();
    expect($first->json('data'))->toHaveCount(1);

    $watermark = $first->json('meta.synced_at');

    $second = $this->getJson('/api/v1/mailbox?updated_since='.$watermark)->assertOk();
    expect($second->json('data'))->toHaveCount(0)
        ->and($second->json('meta.is_delta'))->toBeTrue();
});

it('does not re-send the boundary row on every sync', function (): void {
    $me = User::factory()->create();
    $delivery = deliveredTo($me);
    Sanctum::actingAs($me);

    // A client that naively sends the row's own timestamp must not get it back:
    // `>=` would make the delta never empty for a quiet account.
    $at = $delivery->fresh()->updated_at->toIso8601ZuluString();

    $this->getJson('/api/v1/mailbox?updated_since='.$at)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// --- What a delta actually carries -------------------------------------------

it('carries a change made after the watermark', function (): void {
    $me = User::factory()->create();
    $delivery = deliveredTo($me);
    Sanctum::actingAs($me);

    $watermark = $this->getJson('/api/v1/mailbox')->json('meta.synced_at');

    $this->travel(2)->seconds();
    $delivery->markRead();

    $this->getJson('/api/v1/mailbox?updated_since='.$watermark)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $delivery->id);
});

it('syncs the outbox as deliveries move through the postal clock', function (): void {
    $me = User::factory()->create();
    $recipient = User::factory()->create();
    $letter = Letter::factory()->create(['author_id' => $me->id]);
    $delivery = LetterDelivery::factory()->create([
        'letter_id' => $letter->id,
        'sender_id' => $me->id,
        'recipient_id' => $recipient->id,
    ]);
    Sanctum::actingAs($me);

    $watermark = $this->getJson('/api/v1/deliveries')->json('meta.synced_at');

    $this->travel(2)->seconds();
    $delivery->touch();

    $this->getJson('/api/v1/deliveries?updated_since='.$watermark)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Tombstones ---------------------------------------------------------------

it('reports letters deleted since the watermark', function (): void {
    $me = User::factory()->create();
    $keep = Letter::factory()->create(['author_id' => $me->id]);
    $gone = Letter::factory()->create(['author_id' => $me->id]);
    Sanctum::actingAs($me);

    $watermark = $this->getJson('/api/v1/letters')->json('meta.synced_at');

    $this->travel(2)->seconds();
    $gone->delete();

    $response = $this->getJson('/api/v1/letters?updated_since='.$watermark)->assertOk();

    // A delta only ever carries rows that still exist, so without tombstones
    // the deleted draft would linger on the device forever.
    expect($response->json('meta.deleted_ids'))->toBe([$gone->id])
        ->and(collect($response->json('data'))->pluck('id'))->not->toContain($keep->id);
});

it('never leaks another author\'s tombstones', function (): void {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    $theirs = Letter::factory()->create(['author_id' => $stranger->id]);
    Sanctum::actingAs($me);

    $watermark = $this->getJson('/api/v1/letters')->json('meta.synced_at');

    $this->travel(2)->seconds();
    $theirs->delete();

    $this->getJson('/api/v1/letters?updated_since='.$watermark)
        ->assertOk()
        ->assertJsonPath('meta.deleted_ids', []);
});

it('sends no tombstones on a full sync — absence already means deleted', function (): void {
    $me = User::factory()->create();
    Letter::factory()->create(['author_id' => $me->id])->delete();
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/letters')
        ->assertOk()
        ->assertJsonPath('meta.deleted_ids', [])
        ->assertJsonPath('meta.is_delta', false);
});

// --- Rejecting nonsense loudly ------------------------------------------------

it('rejects an unparseable updated_since instead of syncing silently wrong', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/mailbox?updated_since=ayer')
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_UPDATED_SINCE');
});

it('rejects nonsense on every syncing endpoint', function (string $path): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson($path.'?updated_since=not-a-date')
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_UPDATED_SINCE');
})->with(['/api/v1/mailbox', '/api/v1/deliveries', '/api/v1/letters', '/api/v1/notifications']);

it('treats an empty updated_since as a full sync', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/mailbox?updated_since=')
        ->assertOk()
        ->assertJsonPath('meta.is_delta', false);
});

// --- Isolation still holds ----------------------------------------------------

it('does not let a delta reach into someone else\'s mailbox', function (): void {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    deliveredTo($stranger);
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/mailbox?updated_since='.now()->subDay()->toIso8601ZuluString())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('keeps in_transit out of the mailbox delta', function (): void {
    $me = User::factory()->create();
    $sender = User::factory()->create();
    $letter = Letter::factory()->create(['author_id' => $sender->id]);
    LetterDelivery::factory()->create([
        'letter_id' => $letter->id,
        'sender_id' => $sender->id,
        'recipient_id' => $me->id,
        'status' => DeliveryStatus::InTransit,
    ]);
    Sanctum::actingAs($me);

    // The surprise is the product: a delta must not become a side channel
    // that reveals a letter still in transit.
    $this->getJson('/api/v1/mailbox?updated_since='.now()->subDay()->toIso8601ZuluString())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
