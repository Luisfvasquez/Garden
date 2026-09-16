<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\FeatureFlag;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\ModerationAction;
use App\Models\User;
use App\Notifications\CriticalModerationAlertNotification;
use App\Services\Postal\ArrayRandomRecipientPool;
use App\Services\Postal\RandomRecipientPool;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'bottle_at_sea'], ['enabled' => true]);
    $this->pool = new ArrayRandomRecipientPool;
    $this->pool->replace(['someone']); // non-empty by default
    app()->instance(RandomRecipientPool::class, $this->pool);
});

function eligibleSender(): User
{
    $u = User::factory()->create(['email_verified_at' => now()]);
    $u->forceFill(['created_at' => now()->subDays(30)])->save();

    return $u->refresh();
}

function randomBody(string $text = 'Hola, quienquiera que seas. Hoy el cielo estaba precioso.'): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
}

function randomDraft(User $author, string $text = 'Un saludo desde la orilla, para nadie en particular.'): Letter
{
    $body = randomBody($text);

    return Letter::factory()->for($author, 'author')->create([
        'body' => $body,
        'body_plain' => $text,
        'is_locked' => false,
    ]);
}

function sendRandom($test, string $letterId, array $payload = [])
{
    return $test->postJson(
        "/api/v1/letters/{$letterId}/send-random",
        array_merge(['tier' => 'standard'], $payload),
        ['Idempotency-Key' => (string) Str::uuid()],
    );
}

it('404s when the feature flag is off', function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'bottle_at_sea'], ['enabled' => false]);
    $sender = eligibleSender();
    Sanctum::actingAs($sender);

    sendRandom($this, randomDraft($sender)->id)->assertNotFound();
});

it('queues a bottle at sea with no recipient and returns the daily quota left', function (): void {
    $sender = eligibleSender();
    Sanctum::actingAs($sender);

    $response = sendRandom($this, randomDraft($sender)->id)->assertCreated();

    $response
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.recipient', null)
        ->assertJsonPath('data.quota_remaining_today', 2);

    $delivery = LetterDelivery::firstWhere('sender_id', $sender->id);
    expect($delivery->delivery_mode->value)->toBe('random')
        ->and($delivery->recipient_id)->toBeNull()
        ->and($delivery->is_anonymous)->toBeTrue();
});

it('rejects a sender whose account is too new', function (): void {
    $sender = User::factory()->create(['email_verified_at' => now()]); // created just now
    Sanctum::actingAs($sender);

    sendRandom($this, randomDraft($sender)->id)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'ACCOUNT_TOO_NEW');
});

it('rejects an unverified sender', function (): void {
    $sender = User::factory()->unverified()->create();
    $sender->forceFill(['created_at' => now()->subDays(30)])->save();
    Sanctum::actingAs($sender->refresh());

    sendRandom($this, randomDraft($sender)->id)
        ->assertStatus(403); // `verified` middleware short-circuits first
});

it('rejects a sender with an active restrict_random action', function (): void {
    $sender = eligibleSender();
    ModerationAction::create([
        'user_id' => $sender->id,
        'type' => 'restrict_random',
        'source' => 'manual',
        'expires_at' => now()->addWeek(),
    ]);
    Sanctum::actingAs($sender);

    sendRandom($this, randomDraft($sender)->id)
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'RANDOM_RESTRICTED');
});

it('enforces the daily quota', function (): void {
    $sender = eligibleSender();
    LetterDelivery::factory()->count(3)->create([
        'sender_id' => $sender->id,
        'delivery_mode' => 'random',
        'recipient_id' => null,
    ]);
    Sanctum::actingAs($sender);

    sendRandom($this, randomDraft($sender)->id)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'QUOTA_EXCEEDED');
});

it('blocks a letter that fails the content filter', function (): void {
    $sender = eligibleSender();
    Sanctum::actingAs($sender);
    $letter = randomDraft($sender, 'Te voy a matar cuando te encuentre, no puedes esconderte de mí.');

    sendRandom($this, $letter->id)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'CONTENT_FLAGGED');

    expect(LetterDelivery::where('sender_id', $sender->id)->count())->toBe(0);
});

it('holds a self-harm letter for human review instead of blocking it', function (): void {
    $sender = eligibleSender();
    Sanctum::actingAs($sender);
    $letter = randomDraft($sender, 'A veces pienso que no quiero seguir viviendo, y necesitaba decirlo.');

    $response = sendRandom($this, $letter->id)->assertStatus(202);
    $response->assertJsonPath('data.held_for_review', true);

    $delivery = LetterDelivery::firstWhere('sender_id', $sender->id);
    expect($delivery->status)->toBe(DeliveryStatus::Held)
        ->and($delivery->held_at)->not->toBeNull();
});

it('emails the team when a held random letter is self-harm — the real escalation', function (): void {
    Notification::fake();
    config(['moderation.critical_alert_email' => 'team@evergarden.test']);

    $sender = eligibleSender();
    Sanctum::actingAs($sender);
    $letter = randomDraft($sender, 'A veces pienso que no quiero seguir viviendo, y necesitaba decirlo.');

    sendRandom($this, $letter->id)->assertStatus(202);

    Notification::assertSentOnDemandTimes(CriticalModerationAlertNotification::class, 1);
});

it('rejects with NO_RANDOM_RECIPIENT when the pool is empty', function (): void {
    $this->pool->replace([]);
    $sender = eligibleSender();
    Sanctum::actingAs($sender);

    sendRandom($this, randomDraft($sender)->id)
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'NO_RANDOM_RECIPIENT');
});

it('rejects a letter with attachments', function (): void {
    $sender = eligibleSender();
    $letter = randomDraft($sender);
    $letter->attachments()->create([
        'type' => 'image',
        'disk' => 'public',
        'path' => 'letters/x.jpg',
        'original_name' => 'x.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 10,
        'metadata' => ['width' => 1, 'height' => 1],
    ]);
    Sanctum::actingAs($sender);

    sendRandom($this, $letter->id)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'ATTACHMENTS_NOT_ALLOWED');
});

it('exposes the quota snapshot at GET /random/quota', function (): void {
    $sender = eligibleSender();
    Sanctum::actingAs($sender);

    $this->getJson('/api/v1/random/quota')
        ->assertOk()
        ->assertJsonPath('data.daily_limit', 3)
        ->assertJsonPath('data.daily_remaining', 3)
        ->assertJsonPath('data.eligible', true)
        ->assertJsonPath('data.reasons', []);
});
