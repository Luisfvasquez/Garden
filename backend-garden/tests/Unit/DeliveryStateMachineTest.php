<?php

declare(strict_types=1);

use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Exceptions\ApiException;
use App\Models\LetterDelivery;
use Illuminate\Support\Carbon;

it('moves queued → in_transit and records dispatch + transit events', function (): void {
    $delivery = LetterDelivery::factory()->create();

    $delivery->markInTransit(180, now()->addHours(3), now()->addHours(3)->addMinutes(20));

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::InTransit)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->transit_duration_minutes)->toBe(180)
        ->and($delivery->events()->pluck('event')->all())
        ->toBe([DeliveryEventType::Dispatched, DeliveryEventType::InTransit]);
});

it('moves in_transit → delivered → read, and a second open is a no-op', function (): void {
    $delivery = LetterDelivery::factory()->inTransit()->create();

    $delivery->markDelivered();
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Delivered);

    $delivery->markRead();
    $firstReadAt = $delivery->fresh()->read_at;

    $delivery->markRead(); // idempotent
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Read)
        ->and($delivery->fresh()->read_at->equalTo($firstReadAt))->toBeTrue()
        ->and($delivery->events()->where('event', DeliveryEventType::Read)->count())->toBe(1);
});

it('rejects an out-of-order transition with INVALID_STATE_TRANSITION (409)', function (): void {
    $delivery = LetterDelivery::factory()->create(); // queued

    expect(fn () => $delivery->markDelivered())
        ->toThrow(ApiException::class);

    try {
        $delivery->markRead();
    } catch (ApiException $e) {
        expect($e->errorCode)->toBe('INVALID_STATE_TRANSITION')->and($e->status)->toBe(409);
    }
});

it('cancels from queued', function (): void {
    $delivery = LetterDelivery::factory()->create();

    $delivery->cancel();

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Cancelled)
        ->and($delivery->cancelled_at)->not->toBeNull();
});

it('cancels from in_transit only inside the grace window', function (): void {
    config(['postal.grace_period_minutes' => 15]);

    $fresh = LetterDelivery::factory()->inTransit()->create(['dispatched_at' => now()->subMinutes(5)]);
    $fresh->cancel();
    expect($fresh->fresh()->status)->toBe(DeliveryStatus::Cancelled);

    $stale = LetterDelivery::factory()->inTransit()->create(['dispatched_at' => now()->subMinutes(30)]);
    try {
        $stale->cancel();
        expect()->fail('expected GRACE_PERIOD_EXPIRED');
    } catch (ApiException $e) {
        expect($e->errorCode)->toBe('GRACE_PERIOD_EXPIRED')->and($e->status)->toBe(409);
    }
    expect($stale->fresh()->status)->toBe(DeliveryStatus::InTransit);
});

it('marks blocked silently: status is blocked but the timeline shows a delivery', function (): void {
    $delivery = LetterDelivery::factory()->inTransit()->create();

    $delivery->markBlocked();

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Blocked)
        ->and($delivery->fresh()->status->asSeenBySender())->toBe(DeliveryStatus::Delivered)
        ->and($delivery->events()->pluck('event')->all())->toContain(DeliveryEventType::Delivered);
});

it('marks failed with a reason from queued or in_transit', function (): void {
    $delivery = LetterDelivery::factory()->inTransit()->create();

    $delivery->markFailed('recipient_suspended');

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->fresh()->failure_reason)->toBe('recipient_suspended');
});

it('writes an occurred_at on every event', function (): void {
    Carbon::setTestNow('2026-09-09T12:00:00Z');
    $delivery = LetterDelivery::factory()->create();

    $event = $delivery->recordEvent(DeliveryEventType::SortingOffice, ['office' => 'Leiden']);

    expect($event->occurred_at->toIso8601ZuluString())->toBe('2026-09-09T12:00:00Z')
        ->and($event->metadata)->toBe(['office' => 'Leiden']);
    Carbon::setTestNow();
});
