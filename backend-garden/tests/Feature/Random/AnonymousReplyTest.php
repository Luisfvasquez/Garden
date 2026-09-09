<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\FeatureFlag;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use App\Services\Postal\ArrayRandomRecipientPool;
use App\Services\Postal\RandomRecipientPool;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'bottle_at_sea'], ['enabled' => true]);
    app()->instance(RandomRecipientPool::class, new ArrayRandomRecipientPool);
});

function arrivedRandom(User $sender, User $recipient): LetterDelivery
{
    return LetterDelivery::factory()->create([
        'letter_id' => Letter::factory()->for($sender, 'author')->locked()->create()->id,
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'delivery_mode' => 'random',
        'is_anonymous' => true,
        'status' => DeliveryStatus::Delivered,
        'delivered_at' => now(),
    ]);
}

function replyBody(string $text = 'Gracias por tu carta. Me alegró el día encontrarla.'): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

it('lets the recipient reply once down the anonymous channel', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = arrivedRandom($sender, $recipient);
    Sanctum::actingAs($recipient);

    $this->postJson("/api/v1/mailbox/{$delivery->id}/reply-anonymous", ['body' => replyBody()])
        ->assertCreated()
        ->assertJsonPath('data.recipient', null);

    $delivery->refresh();
    expect($delivery->anonymous_reply_delivery_id)->not->toBeNull();

    $reply = LetterDelivery::find($delivery->anonymous_reply_delivery_id);
    expect($reply->sender_id)->toBe($recipient->id)
        ->and($reply->recipient_id)->toBe($sender->id)
        ->and($reply->delivery_mode->value)->toBe('random')
        ->and($reply->is_anonymous)->toBeTrue();
});

it('closes the channel after the single reply', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = arrivedRandom($sender, $recipient);
    Sanctum::actingAs($recipient);

    $this->postJson("/api/v1/mailbox/{$delivery->id}/reply-anonymous", ['body' => replyBody()])->assertCreated();

    $this->postJson("/api/v1/mailbox/{$delivery->id}/reply-anonymous", ['body' => replyBody('Otra vez')])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'CHANNEL_CLOSED');
});

it('reveals handles only after both parties open correspondence', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = arrivedRandom($sender, $recipient);

    Sanctum::actingAs($recipient);
    $this->postJson("/api/v1/mailbox/{$delivery->id}/open-correspondence")
        ->assertOk()
        ->assertJsonPath('data.opened', false);

    Sanctum::actingAs($sender);
    $this->postJson("/api/v1/mailbox/{$delivery->id}/open-correspondence")
        ->assertOk()
        ->assertJsonPath('data.opened', true);

    // Now the sender's outbox view reveals the stranger.
    $this->getJson("/api/v1/deliveries/{$delivery->id}")
        ->assertOk()
        ->assertJsonPath('data.correspondence_opened', true)
        ->assertJsonPath('data.recipient.postal_handle', $recipient->postal_handle);
});

it('keeps the stranger hidden from the sender until then', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $delivery = arrivedRandom($sender, $recipient);
    Sanctum::actingAs($sender);

    $this->getJson("/api/v1/deliveries/{$delivery->id}")
        ->assertOk()
        ->assertJsonPath('data.recipient', null);
});

it('404s open-correspondence for someone who is neither party', function (): void {
    $delivery = arrivedRandom(User::factory()->create(), User::factory()->create());
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/mailbox/{$delivery->id}/open-correspondence")->assertNotFound();
});
