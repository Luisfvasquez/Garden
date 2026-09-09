<?php

declare(strict_types=1);

use App\Enums\TransitTier;
use App\Models\LetterDelivery;
use App\Models\User;
use App\Services\Postal\TransitCalculator;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->calc = new TransitCalculator;
    Carbon::setTestNow('2026-09-09T12:00:00Z');
});

afterEach(fn () => Carbon::setTestNow());

it('sends immediately when no arrival is requested', function (): void {
    expect($this->calc->scheduledFor(null, TransitTier::Standard, false)->equalTo(now()))->toBeTrue();
});

it('places scheduled_for by subtracting the estimate from the requested arrival', function (): void {
    $arriveAt = now()->addDays(2);

    $scheduled = $this->calc->scheduledFor($arriveAt, TransitTier::Standard, false);
    $estimate = $this->calc->estimateMinutes(TransitTier::Standard, false);

    expect($scheduled->equalTo($arriveAt->copy()->subMinutes($estimate)))->toBeTrue();
});

it('never schedules in the past', function (): void {
    $scheduled = $this->calc->scheduledFor(now()->addMinutes(5), TransitTier::Slow, false);

    expect($scheduled->equalTo(now()))->toBeTrue();
});

it('honours the minimum transit floor', function (): void {
    config(['postal.tiers.express' => ['min' => 1, 'max' => 2, 'monthly_quota' => 3]]);
    config(['postal.minimum_transit_minutes' => 30]);

    expect($this->calc->estimateMinutes(TransitTier::Express, false))->toBe(30);
});

it('applies the cross-border factor', function (): void {
    config(['postal.tiers.standard' => ['min' => 200, 'max' => 200, 'monthly_quota' => null]]);
    config(['postal.geographic_factor.cross_border' => 1.5]);
    config(['postal.transit_jitter' => 0]);

    $same = $this->calc->estimateMinutes(TransitTier::Standard, false);
    $cross = $this->calc->estimateMinutes(TransitTier::Standard, true);

    expect($same)->toBe(200)->and($cross)->toBe(300);
});

it('produces a dispatch plan with transit ≥ floor and an arrival in the future', function (): void {
    $plan = $this->calc->dispatchPlan(TransitTier::Standard, false);

    expect($plan->transitMinutes)->toBeGreaterThanOrEqual((int) config('postal.minimum_transit_minutes'))
        ->and($plan->deliveredAt->greaterThan(now()))->toBeTrue()
        ->and($plan->estimatedDeliveryAt->greaterThan(now()))->toBeTrue();
});

it('reads cross-border from the delivery endpoints', function (): void {
    $delivery = LetterDelivery::factory()->create([
        'sender_id' => User::factory()->create(['country_code' => 'ES']),
        'recipient_id' => User::factory()->create(['country_code' => 'JP']),
    ]);

    expect($this->calc->crossBorderFor($delivery))->toBeTrue();

    $domestic = LetterDelivery::factory()->create([
        'sender_id' => User::factory()->create(['country_code' => 'ES']),
        'recipient_id' => User::factory()->create(['country_code' => 'ES']),
    ]);
    expect($this->calc->crossBorderFor($domestic))->toBeFalse();
});
