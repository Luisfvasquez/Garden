<?php

declare(strict_types=1);

use App\Enums\ModerationCategory;
use App\Enums\ModerationDecision;
use App\Notifications\CriticalModerationAlertNotification;
use App\Services\Moderation\CriticalAlertDispatcher;
use App\Services\Moderation\ModerationVerdict;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->dispatcher = new CriticalAlertDispatcher);

it('always logs, and emails only when an alert address is configured', function (): void {
    Log::spy();
    Notification::fake();
    config(['moderation.critical_alert_email' => '']);

    $this->dispatcher->alert('Critical report filed', 'Someone reported something awful.');

    Log::shouldHaveReceived('critical')->once();
    Notification::assertNothingSent();
});

it('emails the configured address as an on-demand notification', function (): void {
    Notification::fake();
    config(['moderation.critical_alert_email' => 'team@evergarden.test']);

    $this->dispatcher->alert('Critical report filed', 'Someone reported something awful.', ['report_id' => 'r1']);

    Notification::assertSentOnDemand(
        CriticalModerationAlertNotification::class,
        fn (CriticalModerationAlertNotification $n, array $channels, object $notifiable): bool => $n->subject === 'Critical report filed'
            && $notifiable->routes['mail'] === 'team@evergarden.test',
    );
});

it('escalates a critical verdict to an email alert', function (): void {
    Notification::fake();
    config(['moderation.critical_alert_email' => 'team@evergarden.test']);

    $verdict = new ModerationVerdict(ModerationDecision::Flagged, [ModerationCategory::SelfHarm], 0.5, 'local');

    $this->dispatcher->alertIfCritical('Random letter held for review', $verdict, 'Someone needs help.');

    Notification::assertSentOnDemandTimes(CriticalModerationAlertNotification::class, 1);
});

it('only logs a non-critical held verdict — no email', function (): void {
    Log::spy();
    Notification::fake();
    config(['moderation.critical_alert_email' => 'team@evergarden.test']);

    $verdict = new ModerationVerdict(ModerationDecision::Flagged, [ModerationCategory::Spam], 0.5, 'local');

    $this->dispatcher->alertIfCritical('Blog post held for review', $verdict, 'Spammy post.');

    Log::shouldHaveReceived('warning')->once();
    Notification::assertNothingSent();
});

it('does not let a broken mailer bubble up as an exception', function (): void {
    Notification::shouldReceive('route')->andThrow(new RuntimeException('smtp down'));
    Log::spy();
    config(['moderation.critical_alert_email' => 'team@evergarden.test']);

    $this->dispatcher->alert('Critical report filed', 'x');

    Log::shouldHaveReceived('error')->once();
});
