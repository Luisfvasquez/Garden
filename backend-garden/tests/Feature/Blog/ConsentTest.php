<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\FeatureFlag;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\PublicPost;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'blog'], ['enabled' => true]);
});

function receivedLetter(User $sender, User $recipient): LetterDelivery
{
    return LetterDelivery::factory()->create([
        'letter_id' => Letter::factory()->for($sender, 'author')->locked()->create()->id,
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
        'status' => DeliveryStatus::Read,
        'delivered_at' => now(),
    ]);
}

function sharePayload(string $deliveryId): array
{
    return [
        'type' => 'shared_letter',
        'letter_delivery_id' => $deliveryId,
        'title' => 'La carta que me devolvió a mi padre',
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nunca supe decirle esto.']]],
        ]],
        'testimonial' => 'Gracias por escribirla.',
        'is_anonymous' => true,
    ];
}

it('creates a shared letter as pending consent and keeps it out of the feed', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = receivedLetter($sender, $recipient);

    Sanctum::actingAs($recipient);
    $this->postJson('/api/v1/posts', sharePayload($delivery->id))
        ->assertStatus(202)
        ->assertJsonPath('data.consent_status', 'pending');

    $this->getJson('/api/v1/posts')->assertJsonCount(0, 'data');
});

it('shows the pending request to the original author with the exact preview', function (): void {
    $sender = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = receivedLetter($sender, $recipient);

    Sanctum::actingAs($recipient);
    $this->postJson('/api/v1/posts', sharePayload($delivery->id))->assertStatus(202);

    Sanctum::actingAs($sender);
    $this->getJson('/api/v1/consent-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.testimonial', 'Gracias por escribirla.')
        ->assertJsonPath('data.0.is_anonymous', true);
});

it('publishes the post when consent is granted', function (): void {
    $sender = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = receivedLetter($sender, $recipient);

    Sanctum::actingAs($recipient);
    $postId = $this->postJson('/api/v1/posts', sharePayload($delivery->id))->json('data.id');

    Sanctum::actingAs($sender);
    $this->postJson("/api/v1/consent-requests/{$postId}/respond", ['granted' => true])
        ->assertOk()
        ->assertJsonPath('data.consent_status', 'granted');

    expect(PublicPost::find($postId)->published_at)->not->toBeNull();
    $this->getJson('/api/v1/posts')->assertJsonCount(1, 'data');
});

it('locks the letter for 90 days when consent is denied', function (): void {
    $sender = User::factory()->create(['email_verified_at' => now()]);
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = receivedLetter($sender, $recipient);

    Sanctum::actingAs($recipient);
    $postId = $this->postJson('/api/v1/posts', sharePayload($delivery->id))->json('data.id');

    Sanctum::actingAs($sender);
    $this->postJson("/api/v1/consent-requests/{$postId}/respond", ['granted' => false])->assertOk();

    Sanctum::actingAs($recipient);
    $this->postJson('/api/v1/posts', sharePayload($delivery->id))
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'CONSENT_REQUIRED');
});

it('rejects a consent response from someone who is not the original author', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = receivedLetter($sender, $recipient);

    Sanctum::actingAs($recipient);
    $postId = $this->postJson('/api/v1/posts', sharePayload($delivery->id))->json('data.id');

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/consent-requests/{$postId}/respond", ['granted' => true])->assertNotFound();
});

it('refuses to share a letter you did not receive', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['email_verified_at' => now()]);
    $delivery = receivedLetter($sender, $recipient);

    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
    $this->postJson('/api/v1/posts', sharePayload($delivery->id))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_TARGET');
});
