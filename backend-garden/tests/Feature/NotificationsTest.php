<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Jobs\Postal\DeliverArrivedLettersJob;
use App\Jobs\Postal\DispatchSingleLetterJob;
use App\Models\LetterDelivery;
use App\Models\User;
use App\Notifications\LetterArrivedNotification;
use App\Notifications\LetterDeliveredNotification;
use App\Notifications\LetterDispatchedNotification;
use App\Notifications\LetterFailedNotification;
use App\Notifications\LetterReadNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function deliverNow(LetterDelivery $delivery): void
{
    DispatchSingleLetterJob::dispatchSync($delivery->id);
    $delivery->refresh()->forceFill(['delivered_at' => now()->subMinute()])->save();
    (new DeliverArrivedLettersJob)->handle();
}

it('notifies the recipient on arrival and the sender only if they opted in', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $sender->settings()->create(['notify_on_dispatch_confirm' => true]);
    $recipient = User::factory()->create();
    $delivery = LetterDelivery::factory()->create([
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'scheduled_for' => now()->subMinute(),
    ]);

    deliverNow($delivery);

    Notification::assertSentToTimes($recipient, LetterArrivedNotification::class, 1);
    Notification::assertSentToTimes($sender, LetterDeliveredNotification::class, 1);
    Notification::assertSentToTimes($sender, LetterDispatchedNotification::class, 1);
});

it('stays silent for the sender who did not opt into dispatch confirmations', function (): void {
    Notification::fake();

    $delivery = LetterDelivery::factory()->create(['scheduled_for' => now()->subMinute()]);
    deliverNow($delivery);

    Notification::assertNotSentTo($delivery->sender, LetterDeliveredNotification::class);
    Notification::assertSentTo($delivery->recipient, LetterArrivedNotification::class);
});

it('respects notify_on_arrival = false', function (): void {
    $recipient = User::factory()->create();
    $recipient->settings()->create(['notify_on_arrival' => false]);
    $delivery = LetterDelivery::factory()->create([
        'recipient_id' => $recipient->id,
        'scheduled_for' => now()->subMinute(),
    ]);

    deliverNow($delivery);

    expect($recipient->notifications()->count())->toBe(0);
});

it('never puts the real sender in an anonymous arrival notification', function (): void {
    $recipient = User::factory()->create();
    $sender = User::factory()->create(['name' => 'Violet']);
    $delivery = LetterDelivery::factory()->create([
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'is_anonymous' => true,
        'scheduled_for' => now()->subMinute(),
    ]);

    deliverNow($delivery);

    $data = $recipient->notifications()->first()->data;
    expect($data['type'])->toBe('letter.arrived')
        ->and($data['sender']['postal_handle'])->toBeNull()
        ->and(json_encode($data))->not->toContain('Violet')->not->toContain($sender->postal_handle);
});

it('notifies the sender when a delivery fails', function (): void {
    Notification::fake();

    $delivery = LetterDelivery::factory()->create([
        'recipient_id' => User::factory()->create(['status' => UserStatus::Suspended]),
    ]);

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    Notification::assertSentTo($delivery->sender, LetterFailedNotification::class);
});

it('notifies the sender when the recipient opens the letter (receipts on both sides)', function (): void {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $recipient->settings()->create(['share_read_receipts' => true]);
    $delivery = LetterDelivery::factory()->delivered()->create([
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'allow_read_receipt' => true,
    ]);

    Sanctum::actingAs($recipient);
    $this->postJson("/api/v1/mailbox/{$delivery->id}/open")->assertOk();

    Notification::assertSentTo($sender, LetterReadNotification::class);
});

it('lists, counts and clears notifications', function (): void {
    $me = User::factory()->create();
    $delivery = LetterDelivery::factory()->delivered()->create(['recipient_id' => $me->id]);
    $me->notify(new LetterArrivedNotification($delivery));
    $me->notify(new LetterArrivedNotification($delivery));
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at']], 'meta' => ['next_cursor']]);

    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 2);

    $first = $me->notifications()->first()->id;
    $this->postJson("/api/v1/notifications/{$first}/read")->assertNoContent();
    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 1);

    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 0);
});

it('404s marking someone else notification read', function (): void {
    $other = User::factory()->create();
    $delivery = LetterDelivery::factory()->delivered()->create(['recipient_id' => $other->id]);
    $other->notify(new LetterArrivedNotification($delivery));
    $theirNotification = $other->notifications()->first()->id;

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/notifications/{$theirNotification}/read")->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});
