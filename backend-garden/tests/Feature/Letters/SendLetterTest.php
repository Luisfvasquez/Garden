<?php

declare(strict_types=1);

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function sendPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'recipients' => [['postal_handle' => 'placeholder']],
        'delivery' => ['tier' => 'standard', 'arrive_at' => null],
        'is_anonymous' => false,
        'allow_read_receipt' => true,
    ], $overrides);
}

it('creates one queued delivery per recipient and locks the letter', function (): void {
    $author = User::factory()->create();
    $letter = Letter::factory()->for($author, 'author')->create();
    $hana = User::factory()->create(['name' => 'Hana']);
    $leon = User::factory()->create();
    Sanctum::actingAs($author);

    $response = $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $hana->postal_handle], ['postal_handle' => $leon->postal_handle]],
    ]))->assertCreated();

    expect($response->json('data'))->toHaveCount(2);
    $response
        ->assertJsonPath('data.0.status', 'queued')
        ->assertJsonPath('data.0.can_cancel', true)
        ->assertJsonPath('data.0.recipient.postal_handle', $hana->postal_handle)
        ->assertJsonPath('data.0.recipient.display_name', 'Hana');

    expect($response->json('data.0.scheduled_for'))->toMatch('/Z$/')
        ->and($response->json('data.0.estimated_delivery_at'))->toMatch('/Z$/');

    expect($letter->fresh()->is_locked)->toBeTrue();

    $delivery = LetterDelivery::firstWhere('recipient_id', $hana->id);
    expect($delivery->status)->toBe(DeliveryStatus::Queued)
        ->and($delivery->events()->pluck('event')->all())
        ->toBe([DeliveryEventType::Created, DeliveryEventType::Queued]);
});

it('schedules backwards from a requested arrival', function (): void {
    $letter = Letter::factory()->create();
    $recipient = User::factory()->create();
    Sanctum::actingAs($letter->author);

    $arriveAt = now()->addDays(3)->startOfMinute();

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $recipient->postal_handle]],
        'delivery' => ['tier' => 'slow', 'arrive_at' => $arriveAt->toIso8601ZuluString()],
    ]))->assertCreated();

    $delivery = LetterDelivery::firstWhere('recipient_id', $recipient->id);
    expect($delivery->scheduled_for->lessThan($arriveAt))->toBeTrue()
        ->and($delivery->scheduled_for->greaterThan(now()))->toBeTrue();
});

it('rejects an unknown recipient handle with RECIPIENT_NOT_FOUND', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => 'ghost-0000']],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'RECIPIENT_NOT_FOUND');
});

it('refuses to send to yourself', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $letter->author->postal_handle]],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipients');
});

it('requires a verified email', function (): void {
    $author = User::factory()->unverified()->create();
    $letter = Letter::factory()->for($author, 'author')->create();
    $recipient = User::factory()->create();
    Sanctum::actingAs($author);

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $recipient->postal_handle]],
    ]))
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'EMAIL_NOT_VERIFIED');
});

it('404s sending a letter you do not own', function (): void {
    $letter = Letter::factory()->create();
    $recipient = User::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $recipient->postal_handle]],
    ]))->assertNotFound();
});

it('enforces the monthly express quota', function (): void {
    $author = User::factory()->create();
    Sanctum::actingAs($author);
    LetterDelivery::factory()->count(3)->create([
        'sender_id' => $author->id,
        'tier' => 'express',
    ]);

    $letter = Letter::factory()->for($author, 'author')->create();
    $recipient = User::factory()->create();

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $recipient->postal_handle]],
        'delivery' => ['tier' => 'express', 'arrive_at' => null],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'QUOTA_EXCEEDED');
});

it('rejects random mode until Fase 2', function (): void {
    $letter = Letter::factory()->create();
    $recipient = User::factory()->create();
    Sanctum::actingAs($letter->author);

    $this->postJson("/api/v1/letters/{$letter->id}/send", sendPayload([
        'recipients' => [['postal_handle' => $recipient->postal_handle]],
        'delivery' => ['tier' => 'standard', 'mode' => 'random', 'arrive_at' => null],
    ]))->assertStatus(422)->assertJsonValidationErrors('delivery.mode');
});

it('replays an identical Idempotency-Key without sending twice', function (): void {
    $letter = Letter::factory()->create();
    $recipient = User::factory()->create();
    Sanctum::actingAs($letter->author);

    $payload = sendPayload(['recipients' => [['postal_handle' => $recipient->postal_handle]]]);
    $headers = ['Idempotency-Key' => (string) Str::uuid()];

    $first = $this->postJson("/api/v1/letters/{$letter->id}/send", $payload, $headers)->assertCreated();
    $second = $this->postJson("/api/v1/letters/{$letter->id}/send", $payload, $headers)->assertCreated();

    expect($second->headers->get('Idempotency-Replay'))->toBe('true')
        ->and($second->json('data.0.id'))->toBe($first->json('data.0.id'))
        ->and(LetterDelivery::where('recipient_id', $recipient->id)->count())->toBe(1);
});
