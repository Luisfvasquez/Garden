<?php

declare(strict_types=1);

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Enums\UserStatus;
use App\Jobs\Postal\DeliverArrivedLettersJob;
use App\Jobs\Postal\DispatchDueLettersJob;
use App\Jobs\Postal\DispatchSingleLetterJob;
use App\Models\Block;
use App\Models\LetterDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('claims only due, queued deliveries and fans out one job each', function (): void {
    Queue::fake();

    $due = LetterDelivery::factory()->count(2)->create(['scheduled_for' => now()->subMinute()]);
    LetterDelivery::factory()->create(['scheduled_for' => now()->addHour()]); // not due
    LetterDelivery::factory()->inTransit()->create(['scheduled_for' => now()->subDay()]); // not queued

    (new DispatchDueLettersJob)->handle();

    Queue::assertPushed(DispatchSingleLetterJob::class, 2);
    $due->each(fn (LetterDelivery $d) => expect($d->fresh()->dispatch_batch_id)->not->toBeNull());
});

it('dispatches a queued delivery into transit with tracking events', function (): void {
    $delivery = LetterDelivery::factory()->create();

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::InTransit)
        ->and($delivery->dispatched_at)->not->toBeNull()
        ->and($delivery->transit_duration_minutes)->toBeGreaterThan(0)
        ->and($delivery->events()->pluck('event')->all())
        ->toContain(DeliveryEventType::Dispatched, DeliveryEventType::InTransit, DeliveryEventType::SortingOffice);
});

it('is a no-op when the delivery already left queued', function (): void {
    $delivery = LetterDelivery::factory()->inTransit()->create();
    $before = $delivery->dispatched_at;

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    expect($delivery->fresh()->dispatched_at->equalTo($before))->toBeTrue();
});

it('fails a delivery whose recipient is no longer active', function (): void {
    $delivery = LetterDelivery::factory()->create([
        'recipient_id' => User::factory()->create(['status' => UserStatus::Suspended]),
    ]);

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->fresh()->failure_reason)->toBe('recipient_unavailable');
});

it('marks a delivery blocked silently when the recipient blocked the sender', function (): void {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    Block::create(['blocker_id' => $recipient->id, 'blocked_id' => $sender->id]);

    $delivery = LetterDelivery::factory()->create([
        'sender_id' => $sender->id,
        'recipient_id' => $recipient->id,
    ]);

    DispatchSingleLetterJob::dispatchSync($delivery->id);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Blocked)
        ->and($delivery->status->asSeenBySender())->toBe(DeliveryStatus::Delivered)
        ->and($delivery->events()->pluck('event')->all())->toContain(DeliveryEventType::Delivered);
});

it('delivers in-transit letters whose target time has passed', function (): void {
    $arrived = LetterDelivery::factory()->inTransit()->create(['delivered_at' => now()->subMinute()]);
    $stillFlying = LetterDelivery::factory()->inTransit()->create(['delivered_at' => now()->addHour()]);

    (new DeliverArrivedLettersJob)->handle();

    expect($arrived->fresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and($stillFlying->fresh()->status)->toBe(DeliveryStatus::InTransit);
});

it('carries a letter from queued to the mailbox across the full clock', function (): void {
    $delivery = LetterDelivery::factory()->create(['scheduled_for' => now()->subMinute()]);

    (new DispatchDueLettersJob)->handle(); // claims + would fan out
    DispatchSingleLetterJob::dispatchSync($delivery->id);
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::InTransit);

    $this->travelTo($delivery->fresh()->delivered_at->addMinute());
    (new DeliverArrivedLettersJob)->handle();

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and($delivery->fresh()->status->isInMailbox())->toBeTrue();
    $this->travelBack();
});
