<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Jobs\Maintenance\GenerateUpcomingDeliveriesJob;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\LetterSchedule;
use App\Models\User;
use App\Services\Scheduling\OccurrenceGenerator;
use App\Services\Scheduling\OccurrenceMaterializer;

beforeEach(function (): void {
    $this->travelTo('2026-09-09T12:00:00Z');
});

afterEach(fn () => $this->travelBack());

function run_generator(): void
{
    app(GenerateUpcomingDeliveriesJob::class)->handle(
        app(OccurrenceGenerator::class),
        app(OccurrenceMaterializer::class),
    );
}

it('materialises only occurrences inside the 90-day horizon that have a letter', function (): void {
    $owner = User::factory()->create();
    $letter = Letter::factory()->for($owner, 'author')->create();

    $schedule = LetterSchedule::factory()->for($owner, 'user')->create([
        'recurrence_type' => 'weekly',
        'anchor_date' => now()->addDays(3)->toDateString(),
        'occurrences_total' => 20,          // ~140 days of weekly occurrences
        'letter_id' => $letter->id,
        'local_time' => '09:00',
        'timezone' => 'UTC',
    ]);

    run_generator();

    // 90 days / 7 ≈ 12-13 weekly occurrences from day 3.
    $count = LetterDelivery::where('schedule_id', $schedule->id)->count();
    expect($count)->toBeGreaterThanOrEqual(12)->toBeLessThanOrEqual(14);

    $furthest = LetterDelivery::where('schedule_id', $schedule->id)->max('scheduled_for');
    expect(now()->diffInDays($furthest))->toBeLessThanOrEqual(91);
});

it('is idempotent — a second run adds nothing', function (): void {
    $owner = User::factory()->create();
    $letter = Letter::factory()->for($owner, 'author')->create();
    LetterSchedule::factory()->for($owner, 'user')->create([
        'recurrence_type' => 'once',
        'anchor_date' => now()->addDays(10)->toDateString(),
        'letter_id' => $letter->id,
        'timezone' => 'UTC',
    ]);

    run_generator();
    run_generator();

    expect(LetterDelivery::count())->toBe(1);
});

it('skips occurrences with no letter assigned', function (): void {
    $owner = User::factory()->create();
    LetterSchedule::factory()->for($owner, 'user')->create([
        'recurrence_type' => 'once',
        'anchor_date' => now()->addDays(10)->toDateString(),
        'letter_id' => null,
        'timezone' => 'UTC',
    ]);

    run_generator();

    expect(LetterDelivery::count())->toBe(0);
});

it('places scheduled_for before the target arrival', function (): void {
    $owner = User::factory()->create();
    $letter = Letter::factory()->for($owner, 'author')->create();
    $schedule = LetterSchedule::factory()->for($owner, 'user')->create([
        'recurrence_type' => 'once',
        'anchor_date' => now()->addDays(40)->toDateString(),
        'letter_id' => $letter->id,
        'local_time' => '09:00',
        'timezone' => 'UTC',
        'tier' => 'standard',
    ]);

    run_generator();

    $delivery = LetterDelivery::where('schedule_id', $schedule->id)->first();
    $arrival = now()->addDays(40)->setTime(9, 0);

    expect($delivery->status)->toBe(DeliveryStatus::Queued)
        ->and($delivery->scheduled_for->lessThan($arrival))->toBeTrue()
        ->and($delivery->estimated_delivery_at)->not->toBeNull();
});

it('ignores paused schedules', function (): void {
    $owner = User::factory()->create();
    $letter = Letter::factory()->for($owner, 'author')->create();
    LetterSchedule::factory()->for($owner, 'user')->paused()->create([
        'recurrence_type' => 'once',
        'anchor_date' => now()->addDays(10)->toDateString(),
        'letter_id' => $letter->id,
        'timezone' => 'UTC',
    ]);

    run_generator();

    expect(LetterDelivery::count())->toBe(0);
});

it('ignores posthumous / inactivity schedules for now', function (): void {
    $owner = User::factory()->create();
    $letter = Letter::factory()->for($owner, 'author')->create();
    LetterSchedule::factory()->for($owner, 'user')->create([
        'recurrence_type' => 'once',
        'trigger_type' => 'posthumous',
        'anchor_date' => now()->addDays(10)->toDateString(),
        'letter_id' => $letter->id,
        'timezone' => 'UTC',
    ]);

    run_generator();

    expect(LetterDelivery::count())->toBe(0);
});
