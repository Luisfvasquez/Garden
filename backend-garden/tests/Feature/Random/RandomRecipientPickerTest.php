<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Jobs\Postal\DispatchSingleLetterJob;
use App\Jobs\Postal\RefreshRandomRecipientPoolJob;
use App\Models\Block;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\ModerationAction;
use App\Models\User;
use App\Services\Postal\ArrayRandomRecipientPool;
use App\Services\Postal\RandomRecipientPicker;
use App\Services\Postal\RandomRecipientPool;
use App\Services\Postal\RandomRecipientQuery;

beforeEach(function (): void {
    $this->pool = new ArrayRandomRecipientPool;
    app()->instance(RandomRecipientPool::class, $this->pool);
});

function eligibleRecipient(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'accepts_random_letters' => true,
        'last_active_at' => now(),
    ], $overrides));
}

function picker(): RandomRecipientPicker
{
    return new RandomRecipientPicker(app(RandomRecipientPool::class), app(RandomRecipientQuery::class));
}

it('RefreshRandomRecipientPoolJob only pools opted-in, active, verified users', function (): void {
    $in = eligibleRecipient();
    eligibleRecipient(['accepts_random_letters' => false]);
    eligibleRecipient(['email_verified_at' => null]);
    eligibleRecipient(['last_active_at' => now()->subMonths(2)]);

    app(RefreshRandomRecipientPoolJob::class)->handle(app(RandomRecipientQuery::class), $this->pool);

    expect($this->pool->all())->toBe([$in->id]);
});

it('excludes a user with an active restrict_random from the pool', function (): void {
    $restricted = eligibleRecipient();
    ModerationAction::create([
        'user_id' => $restricted->id,
        'type' => 'restrict_random',
        'source' => 'manual',
        'expires_at' => now()->addWeek(),
    ]);

    app(RefreshRandomRecipientPoolJob::class)->handle(app(RandomRecipientQuery::class), $this->pool);

    expect($this->pool->all())->toBe([]);
});

it('picks an eligible stranger and skips blocked / capped ones', function (): void {
    $sender = User::factory()->create();
    $blocked = eligibleRecipient();
    Block::create(['blocker_id' => $blocked->id, 'blocked_id' => $sender->id]);

    $good = eligibleRecipient();

    $this->pool->replace([$blocked->id, $good->id]);

    expect(picker()->pick($sender)?->id)->toBe($good->id);
});

it('returns null and drops stale entries when nobody verifies', function (): void {
    $sender = User::factory()->create();
    $ghost = eligibleRecipient(['accepts_random_letters' => false]);
    $this->pool->replace([$ghost->id]);

    expect(picker()->pick($sender))->toBeNull()
        ->and($this->pool->all())->toBe([]);
});

it('does not pick someone who got a random letter from this sender in the last 90 days', function (): void {
    $sender = User::factory()->create();
    $recent = eligibleRecipient();
    LetterDelivery::factory()->create([
        'sender_id' => $sender->id,
        'recipient_id' => $recent->id,
        'delivery_mode' => 'random',
        'created_at' => now()->subDays(10),
    ]);
    $this->pool->replace([$recent->id]);

    expect(picker()->pick($sender))->toBeNull();
});

it('resolves the recipient at dispatch and fails to drafts when the pool is empty', function (): void {
    $sender = User::factory()->create();
    $letter = Letter::factory()->for($sender, 'author')->locked()->create();
    $delivery = LetterDelivery::factory()->create([
        'letter_id' => $letter->id,
        'sender_id' => $sender->id,
        'recipient_id' => null,
        'delivery_mode' => 'random',
        'scheduled_for' => now()->subMinute(),
    ]);

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->failure_reason)->toBe('no_recipient')
        ->and($letter->fresh()->is_locked)->toBeFalse();
});

it('resolves a real stranger at dispatch and sends the letter into transit', function (): void {
    $sender = User::factory()->create(['country_code' => 'ES']);
    $stranger = eligibleRecipient(['country_code' => 'ES']);
    $this->pool->replace([$stranger->id]);

    $delivery = LetterDelivery::factory()->create([
        'letter_id' => Letter::factory()->for($sender, 'author')->create()->id,
        'sender_id' => $sender->id,
        'recipient_id' => null,
        'delivery_mode' => 'random',
        'scheduled_for' => now()->subMinute(),
    ]);

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::InTransit)
        ->and($delivery->recipient_id)->toBe($stranger->id);
});
