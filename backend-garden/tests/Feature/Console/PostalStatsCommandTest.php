<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\LetterDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The first command docs/runbook.md §1 tells you to run. It is read-only: the
 * point of these tests is that the numbers are right and that it never
 * "helpfully" repairs anything on its way past.
 */
it('runs on an empty database without dividing by zero', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    $this->artisan('postal:stats')
        ->expectsOutputToContain('ninguna')
        ->assertSuccessful();
});

it('counts deliveries by status, including the empty ones', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->count(2)->create(['status' => DeliveryStatus::Queued]);
    LetterDelivery::factory()->delivered()->count(3)->create();

    $this->artisan('postal:stats')
        ->expectsOutputToContain('queued')
        ->expectsOutputToContain('delivered')
        ->expectsOutputToContain('blocked')
        ->assertSuccessful();
});

it('reports the age of the oldest queued delivery and the last dispatch', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subMinutes(90),
    ]);
    LetterDelivery::factory()->inTransit()->create([
        'dispatched_at' => now()->subMinutes(5),
        'delivered_at' => now()->addHours(3),
    ]);

    $this->artisan('postal:stats')
        ->expectsOutputToContain('1h 30m')   // the oldest queued, late by 90 minutes
        ->expectsOutputToContain('5 min')    // …and the clock last ticked 5 minutes ago
        ->assertSuccessful();
});

it('averages the transit that recipients actually waited', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    // Two deliveries, two and four hours of real transit → three hours.
    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Delivered,
        'dispatched_at' => now()->subHours(5),
        'delivered_at' => now()->subHours(3),
        'transit_duration_minutes' => 999, // the plan; deliberately not what we report
    ]);
    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Read,
        'dispatched_at' => now()->subHours(6),
        'delivered_at' => now()->subHours(2),
        'transit_duration_minutes' => 999,
    ]);

    $this->artisan('postal:stats')
        ->expectsOutputToContain('3h 00m')
        ->expectsOutputToContain('2 entregas')
        ->assertSuccessful();
});

it('leaves older deliveries out of the transit sample', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Delivered,
        'dispatched_at' => now()->subDays(30),
        'delivered_at' => now()->subDays(30)->addHours(10),
    ]);

    $this->artisan('postal:stats')
        ->expectsOutputToContain('sin entregas recientes')
        ->assertSuccessful();
});

it('counts pending failed jobs', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'postal',
        'payload' => '{}',
        'exception' => 'boom',
        'failed_at' => now(),
    ]);

    $this->artisan('postal:stats')
        ->expectsOutputToContain('failed_jobs pendientes')
        ->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

it('surfaces a stalled clock in the summary, and still exits 0', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
    ]);

    // postal:stats reports; postal:health is the one that judges.
    $this->artisan('postal:stats')
        ->expectsOutputToContain('reloj postal está parado')
        ->expectsOutputToContain('runbook.md')
        ->assertSuccessful();
});

it('says so plainly when everything is moving', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->delivered()->create();

    $this->artisan('postal:stats')
        ->expectsOutputToContain('El reloj se mueve')
        ->assertSuccessful();
});

it('changes nothing it looked at', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    $stuck = LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
        'dispatch_batch_id' => (string) Str::uuid(),
    ]);
    $before = $stuck->only(['status', 'dispatch_batch_id', 'scheduled_for']);

    $this->artisan('postal:stats')->assertSuccessful();

    expect($stuck->fresh()->only(['status', 'dispatch_batch_id', 'scheduled_for']))->toEqual($before);
});
