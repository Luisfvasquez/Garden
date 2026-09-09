<?php

declare(strict_types=1);

use App\Models\FeatureFlag;
use App\Models\PublicPost;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'blog'], ['enabled' => true]);
});

function postPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'reflection',
        'title' => 'Lo que aprendí del silencio',
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'El duelo enseña a escuchar.']]],
        ]],
        'tags' => ['duelo'],
    ], $overrides);
}

it('404s the blog when the feature flag is off', function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'blog'], ['enabled' => false]);

    $this->getJson('/api/v1/posts')->assertNotFound();
});

it('lists only published public posts', function (): void {
    PublicPost::factory()->count(2)->create();
    PublicPost::factory()->draft()->create();
    PublicPost::factory()->flagged()->create();
    PublicPost::factory()->create(['visibility' => 'unlisted']);

    $this->getJson('/api/v1/posts')->assertOk()->assertJsonCount(2, 'data');
});

it('filters the feed by tag', function (): void {
    $me = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($me);
    $this->postJson('/api/v1/posts', postPayload(['tags' => ['perdón']]))->assertCreated();
    $this->postJson('/api/v1/posts', postPayload(['tags' => ['duelo']]))->assertCreated();

    $this->getJson('/api/v1/posts?tag=perdon')->assertOk()->assertJsonCount(1, 'data');
});

it('publishes a reflection straight away', function (): void {
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson('/api/v1/posts', postPayload())
        ->assertCreated()
        ->assertJsonPath('data.type', 'reflection')
        ->assertJsonPath('data.moderation_status', 'approved');

    expect(PublicPost::first()->published_at)->not->toBeNull();
});

it('hides the real author when the post is anonymous', function (): void {
    $me = User::factory()->create(['email_verified_at' => now(), 'pen_name' => 'La viajera']);
    Sanctum::actingAs($me);
    $slug = $this->postJson('/api/v1/posts', postPayload(['is_anonymous' => true]))->json('data.slug');

    // As a stranger.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/posts/{$slug}")
        ->assertOk()
        ->assertJsonPath('data.author.display_name', 'La viajera')
        ->assertJsonPath('data.author.postal_handle', null)
        ->assertJsonPath('data.moderation_status', null);
});

it('holds a self-harm post for review instead of publishing it', function (): void {
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson('/api/v1/posts', postPayload([
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Llevo semanas pensando que no quiero seguir viviendo.']]],
        ]],
    ]))->assertStatus(202)->assertJsonPath('data.moderation_status', 'flagged');

    $this->getJson('/api/v1/posts')->assertJsonCount(0, 'data');
});

it('rejects a post that hard-fails the filter', function (): void {
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson('/api/v1/posts', postPayload([
        'body' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Envíame nudes a mi correo raro@example.com']]],
        ]],
    ]))->assertStatus(422)->assertJsonPath('error_code', 'CONTENT_FLAGGED');

    expect(PublicPost::count())->toBe(0);
});

it('needs a verified email to post', function (): void {
    Sanctum::actingAs(User::factory()->unverified()->create());

    $this->postJson('/api/v1/posts', postPayload())->assertStatus(403);
});

it('caps tags at three', function (): void {
    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));

    $this->postJson('/api/v1/posts', postPayload(['tags' => ['a', 'b', 'c', 'd']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('tags');
});

it('lets only the author edit or delete a post', function (): void {
    $post = PublicPost::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson("/api/v1/posts/{$post->id}", ['title' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/posts/{$post->id}")->assertNotFound();
});

it('returns 404 for an unpublished post to a stranger but 200 to its author', function (): void {
    $author = User::factory()->create();
    $post = PublicPost::factory()->draft()->for($author, 'author')->create();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/posts/{$post->slug}")->assertNotFound();

    Sanctum::actingAs($author);
    $this->getJson("/api/v1/posts/{$post->slug}")->assertOk();
});
