<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\FeatureFlag;
use App\Models\PublicPost;
use App\Models\Reaction;
use App\Models\User;
use App\Notifications\CriticalModerationAlertNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'blog'], ['enabled' => true]);
});

it('adds a comment and lists it publicly', function (): void {
    $post = PublicPost::factory()->create();
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'Esto me tocó de cerca.'])
        ->assertCreated();

    $this->getJson("/api/v1/posts/{$post->id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Esto me tocó de cerca.');
});

it('flattens a reply to a reply into one level', function (): void {
    $post = PublicPost::factory()->create();
    $top = Comment::factory()->create(['post_id' => $post->id]);
    $reply = Comment::factory()->create(['post_id' => $post->id, 'parent_id' => $top->id]);

    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
    $this->postJson("/api/v1/posts/{$post->id}/comments", [
        'body' => 'Respondo a la respuesta',
        'parent_id' => $reply->id,
    ])->assertCreated()->assertJsonPath('data.parent_id', $top->id);
});

it('refuses comments when the author disabled them', function (): void {
    $post = PublicPost::factory()->create(['comments_enabled' => false]);
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'hola'])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'CHANNEL_CLOSED');
});

it('holds a flagged comment out of the public list', function (): void {
    $post = PublicPost::factory()->create();
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'A veces creo que no quiero seguir viviendo.'])
        ->assertStatus(202);

    $this->getJson("/api/v1/posts/{$post->id}/comments")->assertJsonCount(0, 'data');
});

it('emails the team when a held comment is self-harm — the real escalation', function (): void {
    Notification::fake();
    config(['moderation.critical_alert_email' => 'team@evergarden.test']);

    $post = PublicPost::factory()->create();
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'A veces creo que no quiero seguir viviendo.'])
        ->assertStatus(202);

    Notification::assertSentOnDemandTimes(CriticalModerationAlertNotification::class, 1);
});

it('lets the post author delete any comment', function (): void {
    $author = User::factory()->create();
    $post = PublicPost::factory()->for($author, 'author')->create();
    $comment = Comment::factory()->create(['post_id' => $post->id]);

    Sanctum::actingAs($author);
    $this->deleteJson("/api/v1/comments/{$comment->id}")->assertNoContent();
});

it('toggles reactions and never exposes counts', function (): void {
    $post = PublicPost::factory()->create();
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson("/api/v1/posts/{$post->id}/reactions", ['type' => 'candle'])
        ->assertOk()
        ->assertJsonPath('data.my_reactions', ['candle']);

    // Idempotent.
    $this->postJson("/api/v1/posts/{$post->id}/reactions", ['type' => 'candle'])->assertOk();
    expect(Reaction::count())->toBe(1);

    $this->deleteJson("/api/v1/posts/{$post->id}/reactions/candle")
        ->assertOk()
        ->assertJsonPath('data.my_reactions', []);

    $body = $this->getJson("/api/v1/posts/{$post->slug}")->json('data');
    expect($body)->not->toHaveKey('reaction_counts');
});

it('rejects an unknown reaction type', function (): void {
    $post = PublicPost::factory()->create();
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson("/api/v1/posts/{$post->id}/reactions", ['type' => 'thumbsup'])->assertStatus(422);
});
