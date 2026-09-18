<?php

declare(strict_types=1);

use App\Enums\DollRequestStatus;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Models\FeatureFlag;
use App\Models\Letter;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => true]);
});

function draftBody(string $text = 'Querido hermano, han pasado tres años.'): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]],
    ];
}

function openRequest(): array
{
    $client = User::factory()->create(['email_verified_at' => now()]);
    $doll = User::factory()->create(['email_verified_at' => now()]);

    $request = DollRequest::factory()->inProgress()->create([
        'client_id' => $client->id,
        'doll_id' => $doll->id,
    ]);

    return [$request, $client, $doll];
}

// --- Sharing a draft ---------------------------------------------------------

it('lets the doll share a versioned draft', function (): void {
    [$request, , $doll] = openRequest();
    Sanctum::actingAs($doll);

    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['title' => 'Para mi hermano', 'body' => draftBody()],
        'note' => 'Primera versión, dime qué cambiarías.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'draft')
        ->assertJsonPath('data.draft_version', 1)
        ->assertJsonPath('data.draft_payload.title', 'Para mi hermano');
});

it('numbers drafts monotonically per request', function (): void {
    [$request, , $doll] = openRequest();
    Sanctum::actingAs($doll);

    foreach ([1, 2, 3] as $expected) {
        $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
            'draft_payload' => ['body' => draftBody()],
        ])->assertCreated()->assertJsonPath('data.draft_version', $expected);
    }
});

it('refuses a draft from the client', function (): void {
    [$request, $client] = openRequest();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['body' => draftBody()],
    ])->assertNotFound();
});

it('refuses a draft once the channel is closed', function (): void {
    [$request, , $doll] = openRequest();
    $request->cancel();
    Sanctum::actingAs($doll);

    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['body' => draftBody()],
    ])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'CHANNEL_CLOSED');
});

// --- Approving ---------------------------------------------------------------

it('mints a letter owned by the client and completes the request', function (): void {
    [$request, $client, $doll] = openRequest();

    Sanctum::actingAs($doll);
    $draftId = $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['title' => 'Para mi hermano', 'body' => draftBody()],
    ])->json('data.id');

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$draftId}/approve")
        ->assertCreated()
        ->assertJsonPath('data.title', 'Para mi hermano');

    $letter = Letter::first();
    expect($letter->author_id)->toBe($client->id)          // the client owns it
        ->and($letter->doll_request_id)->toBe($request->id) // the Doll is credited
        ->and($letter->is_locked)->toBeFalse()              // still a draft: the client sends it
        ->and($letter->body_plain)->toContain('Querido hermano');

    expect($request->fresh()->status)->toBe(DollRequestStatus::Completed);
    expect(DollChatMessage::find($draftId)->draft_approved_at)->not->toBeNull();
});

it('refuses approval from the doll', function (): void {
    [$request, , $doll] = openRequest();

    Sanctum::actingAs($doll);
    $draftId = $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['body' => draftBody()],
    ])->json('data.id');

    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$draftId}/approve")
        ->assertNotFound();

    expect(Letter::count())->toBe(0);
});

it('refuses a second approval, so a retry cannot mint two letters', function (): void {
    [$request, $client, $doll] = openRequest();

    Sanctum::actingAs($doll);
    $draftId = $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['body' => draftBody()],
    ])->json('data.id');

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$draftId}/approve")->assertCreated();

    // The request is completed now, so the channel is closed first.
    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$draftId}/approve")
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'CHANNEL_CLOSED');

    expect(Letter::count())->toBe(1);
});

it('refuses to approve a plain text message as if it were a draft', function (): void {
    [$request, $client, $doll] = openRequest();
    $message = DollChatMessage::factory()->create([
        'doll_request_id' => $request->id,
        'sender_id' => $doll->id,
    ]);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$message->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'NOT_A_DRAFT');
});

it('refuses to approve a draft belonging to another request', function (): void {
    [$request, $client] = openRequest();
    [$other, , $otherDoll] = openRequest();

    $foreign = DollChatMessage::factory()->draft()->create([
        'doll_request_id' => $other->id,
        'sender_id' => $otherDoll->id,
    ]);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$foreign->id}/approve")
        ->assertNotFound();

    expect(Letter::count())->toBe(0);
});

it('approves from awaiting_client too', function (): void {
    [$request, $client, $doll] = openRequest();

    Sanctum::actingAs($doll);
    $draftId = $this->postJson("/api/v1/doll-requests/{$request->id}/drafts", [
        'draft_payload' => ['body' => draftBody()],
    ])->json('data.id');

    $request->awaitClient();

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/drafts/{$draftId}/approve")
        ->assertCreated();

    expect($request->fresh()->status)->toBe(DollRequestStatus::Completed);
});
