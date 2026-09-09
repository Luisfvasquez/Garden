<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Events\LetterRead;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function inboxDelivery(User $recipient, array $overrides = []): LetterDelivery
{
    return LetterDelivery::factory()->delivered()->create([
        'recipient_id' => $recipient->id,
        ...$overrides,
    ]);
}

it('shows delivered mail as a closed envelope and never leaks the body', function (): void {
    $me = User::factory()->create();
    $delivery = inboxDelivery($me);
    Sanctum::actingAs($me);

    $response = $this->getJson('/api/v1/mailbox')->assertOk()->assertJsonCount(1, 'data');

    $row = $response->json('data.0');
    expect($row)->toHaveKeys(['id', 'status', 'is_opened', 'envelope', 'sender', 'title', 'has_attachments'])
        ->and($row)->not->toHaveKey('body');
    $response->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'has_more']]);
});

it('never returns an in-transit (or queued) delivery to the recipient', function (): void {
    $me = User::factory()->create();
    LetterDelivery::factory()->inTransit()->create(['recipient_id' => $me->id]);
    LetterDelivery::factory()->create(['recipient_id' => $me->id]); // queued
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/mailbox')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/mailbox/unread-count')->assertOk()->assertJsonPath('data.count', 0);
});

it('hides the sender of an anonymous letter until reveal time', function (): void {
    $me = User::factory()->create();
    $hidden = inboxDelivery($me, ['is_anonymous' => true, 'reveal_sender_at' => null]);
    $revealed = inboxDelivery($me, ['is_anonymous' => true, 'reveal_sender_at' => now()->subDay()]);
    Sanctum::actingAs($me);

    $this->getJson("/api/v1/mailbox/{$hidden->id}")
        ->assertOk()
        ->assertJsonPath('data.is_anonymous', true)
        ->assertJsonPath('data.sender.display_name', 'Alguien')
        ->assertJsonPath('data.sender.postal_handle', null);

    $this->getJson("/api/v1/mailbox/{$revealed->id}")
        ->assertOk()
        ->assertJsonPath('data.sender.postal_handle', $revealed->sender->postal_handle);
});

it('filters by unread / read / archived / favorite', function (): void {
    $me = User::factory()->create();
    inboxDelivery($me); // unread
    tap(inboxDelivery($me), fn ($d) => $d->markRead()); // read
    inboxDelivery($me)->setArchived(true); // archived (still "delivered")
    tap(inboxDelivery($me), function ($d) {
        $d->markRead();
        $d->setFavorite(true);
    }); // read + favorite
    Sanctum::actingAs($me);

    expect($this->getJson('/api/v1/mailbox?status=unread')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/mailbox?status=read')->json('data'))->toHaveCount(2);
    expect($this->getJson('/api/v1/mailbox?status=archived')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/mailbox?status=favorite')->json('data'))->toHaveCount(1);
    expect($this->getJson('/api/v1/mailbox')->json('data'))->toHaveCount(3); // archived hidden by default
});

it('opens a letter: full body, marks read, idempotent', function (): void {
    Event::fake([LetterRead::class]);
    $me = User::factory()->create();
    $delivery = inboxDelivery($me);
    Sanctum::actingAs($me);

    $response = $this->postJson("/api/v1/mailbox/{$delivery->id}/open")->assertOk();
    expect($response->json('data.body'))->toHaveKey('type')
        ->and($response->json('data.read_at'))->not->toBeNull();

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Read);

    $this->postJson("/api/v1/mailbox/{$delivery->id}/open")->assertOk(); // idempotent
    expect($delivery->fresh()->events()->where('event', 'read')->count())->toBe(1);
});

it('emits LetterRead only when both sides allow read receipts', function (): void {
    Event::fake([LetterRead::class]);

    $sharer = User::factory()->create();
    UserSettings::factory()->for($sharer)->create(['share_read_receipts' => true]);
    inboxDeliveryOpen($sharer, ['allow_read_receipt' => true]);
    Event::assertDispatched(LetterRead::class);

    Event::fake([LetterRead::class]);
    $quiet = User::factory()->create();
    UserSettings::factory()->for($quiet)->create(['share_read_receipts' => false]);
    inboxDeliveryOpen($quiet, ['allow_read_receipt' => true]);
    Event::assertNotDispatched(LetterRead::class);
});

it('toggles archive and favorite', function (): void {
    $me = User::factory()->create();
    $delivery = inboxDelivery($me);
    Sanctum::actingAs($me);

    $this->postJson("/api/v1/mailbox/{$delivery->id}/archive")->assertOk()->assertJsonPath('data.is_archived', true);
    $this->postJson("/api/v1/mailbox/{$delivery->id}/archive")->assertOk()->assertJsonPath('data.is_archived', false);
    $this->postJson("/api/v1/mailbox/{$delivery->id}/favorite", ['favorite' => true])
        ->assertOk()->assertJsonPath('data.is_favorite', true);
});

it('reply creates a draft owned by the recipient, linked to the delivery, and sends nothing', function (): void {
    $me = User::factory()->create();
    $delivery = inboxDelivery($me);
    Sanctum::actingAs($me);

    $response = $this->postJson("/api/v1/mailbox/{$delivery->id}/reply")->assertCreated();

    $draft = Letter::find($response->json('data.id'));
    expect($draft->author_id)->toBe($me->id)
        ->and($draft->in_reply_to_delivery_id)->toBe($delivery->id)
        ->and($draft->is_locked)->toBeFalse()
        ->and(LetterDelivery::where('letter_id', $draft->id)->count())->toBe(0);
});

it('404s mailbox access for a non-recipient or an unarrived letter', function (): void {
    $me = User::factory()->create();
    $mine = inboxDelivery($me);
    $inTransit = LetterDelivery::factory()->inTransit()->create(['recipient_id' => $me->id]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/mailbox/{$mine->id}")->assertNotFound();

    Sanctum::actingAs($me);
    $this->getJson("/api/v1/mailbox/{$inTransit->id}")->assertNotFound();
    $this->postJson("/api/v1/mailbox/{$inTransit->id}/open")->assertNotFound();
});

function inboxDeliveryOpen(User $recipient, array $overrides): void
{
    $delivery = LetterDelivery::factory()->delivered()->create([
        'recipient_id' => $recipient->id,
        ...$overrides,
    ]);
    test()->actingAs($recipient, 'sanctum');
    test()->postJson("/api/v1/mailbox/{$delivery->id}/open")->assertOk();
}
