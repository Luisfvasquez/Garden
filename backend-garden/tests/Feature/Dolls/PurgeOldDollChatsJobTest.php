<?php

declare(strict_types=1);

use App\Jobs\Dolls\PurgeOldDollChatsJob;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Models\Letter;
use App\Models\User;

function chatFor(DollRequest $request, int $lines = 2): void
{
    $sender = User::factory()->create();

    for ($i = 0; $i < $lines; $i++) {
        DollChatMessage::factory()->create([
            'doll_request_id' => $request->id,
            'sender_id' => $sender->id,
        ]);
    }
}

it('purges the transcript of a request closed over 90 days ago', function (): void {
    $request = DollRequest::factory()->completed()->create(['completed_at' => now()->subDays(91)]);
    chatFor($request);

    (new PurgeOldDollChatsJob)->handle();

    expect(DollChatMessage::count())->toBe(0);
});

it('keeps the request itself — only the conversation goes', function (): void {
    $request = DollRequest::factory()->completed()->create([
        'completed_at' => now()->subDays(120),
        'client_rating' => 5,
        'rated_at' => now()->subDays(119),
    ]);
    chatFor($request);

    (new PurgeOldDollChatsJob)->handle();

    $request->refresh();
    expect(DollRequest::count())->toBe(1)
        ->and($request->client_rating)->toBe(5)
        ->and($request->completed_at)->not->toBeNull();
});

it('keeps the letter the client owns', function (): void {
    $client = User::factory()->create();
    $request = DollRequest::factory()->completed()->create([
        'client_id' => $client->id,
        'completed_at' => now()->subDays(200),
    ]);
    chatFor($request);

    $letter = Letter::factory()->create(['author_id' => $client->id]);
    $letter->forceFill(['doll_request_id' => $request->id])->save();

    (new PurgeOldDollChatsJob)->handle();

    expect(DollChatMessage::count())->toBe(0)
        ->and(Letter::whereKey($letter->id)->exists())->toBeTrue();
});

it('leaves a recently closed conversation alone', function (): void {
    $request = DollRequest::factory()->completed()->create(['completed_at' => now()->subDays(30)]);
    chatFor($request);

    (new PurgeOldDollChatsJob)->handle();

    expect(DollChatMessage::count())->toBe(2);
});

it('never touches an open conversation, however old', function (): void {
    $request = DollRequest::factory()->inProgress()->create([
        'created_at' => now()->subYears(2),
        'updated_at' => now()->subYears(2),
    ]);
    chatFor($request);

    (new PurgeOldDollChatsJob)->handle();

    expect(DollChatMessage::count())->toBe(2);
});

it('purges cancelled and rejected requests too, dated by their own timestamp', function (): void {
    $cancelled = DollRequest::factory()->create([
        'status' => 'cancelled',
        'cancelled_at' => now()->subDays(100),
    ]);
    chatFor($cancelled, 1);

    $rejected = DollRequest::factory()->create([
        'status' => 'rejected',
        'rejected_at' => now()->subDays(100),
    ]);
    chatFor($rejected, 1);

    (new PurgeOldDollChatsJob)->handle();

    expect(DollChatMessage::count())->toBe(0);
});

it('honours a configured retention window', function (): void {
    config(['dolls.chat_retention_days' => 7]);
    $request = DollRequest::factory()->completed()->create(['completed_at' => now()->subDays(10)]);
    chatFor($request);

    (new PurgeOldDollChatsJob)->handle();

    expect(DollChatMessage::count())->toBe(0);
});
