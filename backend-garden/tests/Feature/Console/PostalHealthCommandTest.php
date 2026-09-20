<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\LetterDelivery;
use App\Notifications\PostalClockAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The alarm of backend-garden/docs/jobs-y-colas.md §Alerta crítica. Every case
 * pins the clock with travelTo(): "overdue" only means anything relative to a
 * known now(), and an alert that cries wolf is worse than no alert.
 */
beforeEach(function (): void {
    config()->set('postal.health.alert_email', 'ops@evergarden.test');
    Notification::fake();
});

it('passes when there is nothing due at all', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create(['scheduled_for' => now()->addDay()]);

    $this->artisan('postal:health')->assertSuccessful();
    Notification::assertNothingSent();
});

it('passes when deliveries are due but the clock is still dispatching', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    // Overdue by an hour — but something left the office two minutes ago, so
    // the clock is alive and merely catching up.
    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
    ]);
    LetterDelivery::factory()->inTransit()->create([
        'dispatched_at' => now()->subMinutes(2),
        'delivered_at' => now()->addHours(3),
    ]);

    $this->artisan('postal:health')->assertSuccessful();
    Notification::assertNothingSent();
});

it('passes for a delivery that has only just come due', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    // Due 30 seconds ago and nothing ever dispatched: both ticks run every
    // minute, so this is normal. Without the grace window it would page us.
    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subSeconds(30),
    ]);

    $this->artisan('postal:health')->assertSuccessful();
});

it('fails when deliveries are overdue and nothing has been dispatched for 15 minutes', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->count(3)->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subMinutes(40),
    ]);
    LetterDelivery::factory()->inTransit()->create([
        'dispatched_at' => now()->subMinutes(20),
        'delivered_at' => now()->addHours(3),
    ]);

    $this->artisan('postal:health')->assertFailed();

    Notification::assertSentOnDemand(
        PostalClockAlertNotification::class,
        function (PostalClockAlertNotification $notification, array $channels, object $notifiable): bool {
            return $notifiable->routes['mail'] === 'ops@evergarden.test'
                && str_contains($notification->problems[0], 'reloj postal está parado');
        }
    );
});

it('fails when an in_transit delivery is past its delivered_at and nobody moved it', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    // The dispatcher is alive (something went out a minute ago); it is the
    // delivery tick that is stuck.
    LetterDelivery::factory()->inTransit()->create([
        'dispatched_at' => now()->subMinute(),
        'delivered_at' => now()->subHour(),
    ]);

    $this->artisan('postal:health')->assertFailed();
    Notification::assertSentOnDemand(PostalClockAlertNotification::class);
});

it('fails on rows reserved with a dispatch_batch_id and left queued — runbook §1, causa 2', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    // The worker died between the atomic reservation and the dispatch. These
    // rows are invisible to DispatchDueLettersJob for ever, because it filters
    // on `dispatch_batch_id IS NULL` — even while the clock looks healthy.
    $orphan = LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subMinutes(45),
        'dispatch_batch_id' => (string) Str::uuid(),
    ]);
    $orphan->forceFill(['updated_at' => now()->subMinutes(45)])->saveQuietly();

    LetterDelivery::factory()->inTransit()->create([
        'dispatched_at' => now()->subMinute(),
        'delivered_at' => now()->addHours(3),
    ]);

    $this->artisan('postal:health')
        ->expectsOutputToContain('dispatch_batch_id')
        ->assertFailed();
});

it('leaves a freshly reserved batch alone', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    // Reserved five minutes ago: a worker is plausibly still chewing on it.
    $fresh = LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subMinutes(20),
        'dispatch_batch_id' => (string) Str::uuid(),
    ]);
    $fresh->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

    LetterDelivery::factory()->inTransit()->create([
        'dispatched_at' => now()->subMinute(),
        'delivered_at' => now()->addHours(3),
    ]);

    $this->artisan('postal:health')->assertSuccessful();
});

it('only emails once while the same incident lasts', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
    ]);

    $this->artisan('postal:health')->assertFailed();

    // The scheduler fires again five minutes later and the clock is still dead.
    $this->travelTo('2026-03-01 12:05:00');
    $this->artisan('postal:health')->assertFailed();

    Notification::assertSentOnDemandTimes(PostalClockAlertNotification::class, 1);
});

it('emails again once the cooldown has expired', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
    ]);

    $this->artisan('postal:health')->assertFailed();

    $this->travelTo('2026-03-01 13:30:00');
    $this->artisan('postal:health')->assertFailed();

    Notification::assertSentOnDemandTimes(PostalClockAlertNotification::class, 2);
});

it('still fails, without emailing, when OPS_ALERT_EMAIL is empty', function (): void {
    config()->set('postal.health.alert_email', '');
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
    ]);

    $this->artisan('postal:health')
        ->expectsOutputToContain('OPS_ALERT_EMAIL')
        ->assertFailed();

    Notification::assertNothingSent();
});

it('does not page anyone when run by hand with --no-alert', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Queued,
        'scheduled_for' => now()->subHour(),
    ]);

    $this->artisan('postal:health', ['--no-alert' => true])->assertFailed();
    Notification::assertNothingSent();
});

it('ignores deliveries in a terminal state, however old', function (): void {
    $this->travelTo('2026-03-01 12:00:00');

    LetterDelivery::factory()->count(2)->create([
        'status' => DeliveryStatus::Cancelled,
        'scheduled_for' => now()->subWeek(),
    ]);
    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Failed,
        'scheduled_for' => now()->subWeek(),
    ]);

    // A held random letter waits for a human by design (docs/moderacion.md).
    LetterDelivery::factory()->create([
        'status' => DeliveryStatus::Held,
        'scheduled_for' => now()->subWeek(),
    ]);

    $this->artisan('postal:health')->assertSuccessful();
});
