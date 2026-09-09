<?php

declare(strict_types=1);

use App\Jobs\Maintenance\DispatchDuePushesJob;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\PushSubscription;
use App\Models\ScheduledPush;
use App\Models\User;
use App\Notifications\LetterArrivedNotification;
use App\Services\Push\WebPushClient;
use Illuminate\Support\Carbon;
use Tests\Fakes\RecordingWebPushClient;

beforeEach(function (): void {
    $this->client = new RecordingWebPushClient;
    app()->instance(WebPushClient::class, $this->client);
    config(['webpush.vapid.public_key' => 'x', 'webpush.vapid.private_key' => 'y']);
});

function recipientWithPush(array $settings = []): User
{
    $user = User::factory()->withSettings()->create(['timezone' => 'Europe/Madrid']);
    $user->settings()->update(array_merge(['notify_push' => true, 'notify_on_arrival' => true], $settings));
    PushSubscription::factory()->for($user)->create();

    return $user->load('settings', 'pushSubscriptions');
}

function arrivedDelivery(User $recipient, bool $anonymous = false): LetterDelivery
{
    return LetterDelivery::factory()->create([
        'letter_id' => Letter::factory()->create(['title' => 'Para ti'])->id,
        'sender_id' => User::factory()->create(['name' => 'Gilbert'])->id,
        'recipient_id' => $recipient->id,
        'is_anonymous' => $anonymous,
        'status' => 'delivered',
        'delivered_at' => now(),
    ]);
}

it('queues a push row and sends it on the next job tick', function (): void {
    $user = recipientWithPush();
    $user->notify(new LetterArrivedNotification(arrivedDelivery($user)));

    expect(ScheduledPush::where('user_id', $user->id)->count())->toBe(1);

    app(DispatchDuePushesJob::class)->handle($this->client);

    expect($this->client->sent)->toHaveCount(1)
        ->and($this->client->sent[0]['payload']['title'])->toBe('Ha llegado una carta')
        ->and($this->client->sent[0]['payload']['body'])->toBe('Gilbert te ha escrito.');
    expect(ScheduledPush::first()->sent_at)->not->toBeNull();
});

it('never names the sender in an anonymous arrival push', function (): void {
    $user = recipientWithPush();
    $user->notify(new LetterArrivedNotification(arrivedDelivery($user, anonymous: true)));

    app(DispatchDuePushesJob::class)->handle($this->client);

    expect($this->client->sent[0]['payload']['body'])->toBe('Alguien te ha escrito.')
        ->and($this->client->sent[0]['payload']['body'])->not->toContain('Gilbert');
});

it('holds the push until quiet hours end, then delivers', function (): void {
    Carbon::setTestNow('2026-09-09T23:30:00+02:00'); // 23:30 Madrid
    $user = recipientWithPush(['quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00']);

    $user->notify(new LetterArrivedNotification(arrivedDelivery($user)));

    $row = ScheduledPush::where('user_id', $user->id)->first();
    expect($row->deliver_after->gt(now()))->toBeTrue();

    app(DispatchDuePushesJob::class)->handle($this->client);
    expect($this->client->sent)->toBeEmpty(); // still in quiet hours

    Carbon::setTestNow('2026-09-10T07:05:00+02:00');
    app(DispatchDuePushesJob::class)->handle($this->client);
    expect($this->client->sent)->toHaveCount(1);

    Carbon::setTestNow();
});

it('groups several arrivals in one tick into a single notification', function (): void {
    $user = recipientWithPush();

    foreach (range(1, 3) as $ignored) {
        $user->notify(new LetterArrivedNotification(arrivedDelivery($user)));
    }

    expect(ScheduledPush::where('user_id', $user->id)->count())->toBe(3);

    app(DispatchDuePushesJob::class)->handle($this->client);

    expect($this->client->sent)->toHaveCount(1)
        ->and($this->client->sent[0]['payload']['body'])->toBe('3 novedades nuevas.');
});

it('does not push when the user turned push off', function (): void {
    $user = recipientWithPush(['notify_push' => false]);
    $user->notify(new LetterArrivedNotification(arrivedDelivery($user)));

    expect(ScheduledPush::count())->toBe(0);
});

it('does not push when the user has no subscription', function (): void {
    $user = User::factory()->withSettings()->create();
    $user->settings()->update(['notify_push' => true]);
    $user->notify(new LetterArrivedNotification(arrivedDelivery($user->load('settings'))));

    expect(ScheduledPush::count())->toBe(0);
});

it('prunes a subscription the push service reports as gone', function (): void {
    $user = recipientWithPush();
    $sub = $user->pushSubscriptions()->first();
    $this->client->failEndpoints = [$sub->endpoint];

    $user->notify(new LetterArrivedNotification(arrivedDelivery($user)));
    app(DispatchDuePushesJob::class)->handle($this->client);

    expect(PushSubscription::find($sub->id))->toBeNull();
});
