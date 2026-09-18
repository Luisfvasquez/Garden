<?php

declare(strict_types=1);

use App\Enums\DollChatMessageType;
use App\Enums\DollRequestStatus;
use App\Enums\ModerationActionType;
use App\Events\DollChatMessageSent;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Models\FeatureFlag;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => true]);
});

/** An open chat: request in_progress, both parties verified. */
function openChat(): array
{
    $client = User::factory()->create(['email_verified_at' => now()]);
    $doll = User::factory()->create(['email_verified_at' => now()]);

    $request = DollRequest::factory()->inProgress()->create([
        'client_id' => $client->id,
        'doll_id' => $doll->id,
    ]);

    return [$request, $client, $doll];
}

// --- Participation and isolation --------------------------------------------

it('lets both parties read the transcript', function (): void {
    [$request, $client, $doll] = openChat();
    DollChatMessage::factory()->create(['doll_request_id' => $request->id, 'sender_id' => $doll->id]);

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/doll-requests/{$request->id}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    Sanctum::actingAs($doll);
    $this->getJson("/api/v1/doll-requests/{$request->id}/messages")->assertOk();
});

it('404s the transcript for anyone who is not a participant', function (): void {
    [$request] = openChat();
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->getJson("/api/v1/doll-requests/{$request->id}/messages")->assertNotFound();
    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => 'hola'])
        ->assertNotFound();
});

it('stores a message and returns it to the sender', function (): void {
    [$request, $client] = openChat();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", [
        'body' => '¿Cómo era él cuando eran niños?',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.is_mine', true)
        ->assertJsonPath('data.body', '¿Cómo era él cuando eran niños?');

    expect(DollChatMessage::where('doll_request_id', $request->id)->count())->toBe(1);
});

it('encrypts the body at rest', function (): void {
    [$request, $client] = openChat();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => 'un secreto'])
        ->assertCreated();

    $raw = DB::table('doll_chat_messages')->value('body');
    expect($raw)->not->toContain('un secreto');
    expect(DollChatMessage::first()->body)->toBe('un secreto');
});

it('broadcasts the message on the private channel', function (): void {
    Event::fake([DollChatMessageSent::class]);
    [$request, $client] = openChat();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => 'hola'])
        ->assertCreated();

    Event::assertDispatched(DollChatMessageSent::class, fn (DollChatMessageSent $e) => $e->message->doll_request_id === $request->id);
});

// --- The channel only exists while the request is open -----------------------

it('refuses to write once the request is closed', function (): void {
    [$request, $client] = openChat();
    $request->cancel();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => 'hola'])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'CHANNEL_CLOSED');
});

it('refuses to write while the request is still pending', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $doll = User::factory()->create(['email_verified_at' => now()]);
    $request = DollRequest::factory()->create(['client_id' => $client->id, 'doll_id' => $doll->id]);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => 'hola'])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'CHANNEL_CLOSED');
});

it('still serves the transcript after the request closes', function (): void {
    [$request, $client, $doll] = openChat();
    DollChatMessage::factory()->create(['doll_request_id' => $request->id, 'sender_id' => $doll->id]);
    $request->cancel();

    Sanctum::actingAs($client);
    $this->getJson("/api/v1/doll-requests/{$request->id}/messages")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Whose turn it is --------------------------------------------------------

it('parks the request on the client when the doll writes, and resumes when they answer', function (): void {
    [$request, $client, $doll] = openChat();

    Sanctum::actingAs($doll);
    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => '¿Cómo era él?'])
        ->assertCreated();
    expect($request->fresh()->status)->toBe(DollRequestStatus::AwaitingClient);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", ['body' => 'Callado.'])
        ->assertCreated();
    expect($request->fresh()->status)->toBe(DollRequestStatus::InProgress);
});

// --- Anti contact-exchange filter -------------------------------------------

it('warns both parties without blocking when a message looks like a contact exchange', function (): void {
    [$request, $client] = openChat();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", [
        'body' => 'mejor escríbeme a luis@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.pii_flags', ['email']);

    // The message went through — the filter warns, it does not block.
    $messages = DollChatMessage::where('doll_request_id', $request->id)->orderBy('created_at')->get();
    expect($messages)->toHaveCount(2)
        ->and($messages[0]->body)->toContain('luis@example.com')
        ->and($messages[1]->type)->toBe(DollChatMessageType::System)
        ->and($messages[1]->sender_id)->toBeNull()
        ->and($messages[1]->body)->toContain('correo electrónico');
});

it('records a warn moderation action on contact exchange', function (): void {
    [$request, $client] = openChat();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", [
        'body' => 'mi teléfono es +34 611 22 33 44',
    ])->assertCreated();

    $action = ModerationAction::where('user_id', $client->id)->first();
    expect($action)->not->toBeNull()
        ->and($action->type)->toBe(ModerationActionType::Warn)
        ->and($action->reason)->toBe('contact_exchange_in_doll_chat');
});

it('leaves an ordinary message unflagged', function (): void {
    [$request, $client] = openChat();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/messages", [
        'body' => 'Nos peleamos el 12 de abril de 2021.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.pii_flags', []);

    expect(DollChatMessage::where('doll_request_id', $request->id)->count())->toBe(1);
});

// --- Reporting from inside the chat -----------------------------------------

it('lets a participant report a chat message', function (): void {
    [$request, $client, $doll] = openChat();
    $message = DollChatMessage::factory()->create([
        'doll_request_id' => $request->id,
        'sender_id' => $doll->id,
    ]);

    Sanctum::actingAs($client);
    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'doll_chat_message',
        'reportable_id' => $message->id,
        'category' => 'harassment',
    ])->assertCreated();
});

it('404s a report on a chat message from outside the request', function (): void {
    [$request, , $doll] = openChat();
    $message = DollChatMessage::factory()->create([
        'doll_request_id' => $request->id,
        'sender_id' => $doll->id,
    ]);

    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'doll_chat_message',
        'reportable_id' => $message->id,
        'category' => 'harassment',
    ])->assertNotFound();
});
