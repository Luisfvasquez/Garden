<?php

declare(strict_types=1);

use App\Models\Letter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function docBody(string $text = 'Querida Violet, te escribo desde muy lejos.'): array
{
    return [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        ],
    ];
}

it('creates a draft, encrypts the body at rest and counts words', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/letters', [
        'title' => 'Para cuando cumplas veinte',
        'body' => docBody('uno dos tres cuatro cinco'),
        'style' => ['paper' => 'parchment', 'font' => 'cormorant'],
    ])->assertCreated();

    $response
        ->assertJsonPath('data.word_count', 5)
        ->assertJsonPath('data.reading_time_minutes', 1)
        ->assertJsonPath('data.is_locked', false)
        ->assertJsonPath('data.deliveries_count', 0)
        ->assertJsonPath('data.style.paper', 'parchment');

    $raw = DB::table('letters')->where('id', $response->json('data.id'))->value('body');
    expect($raw)->not->toContain('dos tres')
        ->and(Letter::find($response->json('data.id'))->body_plain)->toContain('uno dos tres');
});

it('strips editor features that are not on the allow-list', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $body = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Titular']]],
            [
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'texto',
                    'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'http://evil']]],
                ]],
            ],
        ],
    ];

    $data = $this->postJson('/api/v1/letters', ['body' => $body])->assertCreated()->json('data.body');

    $types = collect($data['content'])->pluck('type');
    expect($types)->not->toContain('heading')->toContain('paragraph');
    expect($data['content'][0]['content'][0]['marks'])->toBe([['type' => 'bold']]);
});

it('rejects a body over the character limit', function (): void {
    Sanctum::actingAs(User::factory()->create());
    config(['postal.limits.body_max_chars' => 20]);

    $this->postJson('/api/v1/letters', ['body' => docBody(str_repeat('a ', 40))])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

it('enforces the open-draft ceiling', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    config(['postal.limits.max_open_drafts' => 2]);

    Letter::factory()->count(2)->for($user, 'author')->create();

    $this->postJson('/api/v1/letters', ['body' => docBody()])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'DRAFT_LIMIT_REACHED');
});

it('requires authentication and a well-formed body', function (): void {
    $this->postJson('/api/v1/letters', ['body' => docBody()])->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/letters', [])->assertStatus(422)->assertJsonValidationErrors('body');
});

it('lists only the caller letters with a cursor envelope and a status filter', function (): void {
    $me = User::factory()->create();
    Letter::factory()->count(2)->for($me, 'author')->create();
    Letter::factory()->for($me, 'author')->locked()->create();
    Letter::factory()->count(3)->create(); // someone else's

    Sanctum::actingAs($me);

    $all = $this->getJson('/api/v1/letters')->assertOk();
    expect($all->json('data'))->toHaveCount(3);
    $all->assertJsonStructure(['data', 'meta' => ['per_page', 'next_cursor', 'has_more']]);

    $this->getJson('/api/v1/letters?status=sent')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/letters?status=draft')->assertOk()->assertJsonCount(2, 'data');
});

it('shows a draft to its author and 404s for anyone else', function (): void {
    $letter = Letter::factory()->create();

    Sanctum::actingAs($letter->author);
    $this->getJson("/api/v1/letters/{$letter->id}")->assertOk()->assertJsonPath('data.id', $letter->id);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/letters/{$letter->id}")
        ->assertNotFound()
        ->assertJsonPath('error_code', 'NOT_FOUND');
});

it('autosaves partial updates', function (): void {
    $letter = Letter::factory()->create(['title' => 'old']);
    Sanctum::actingAs($letter->author);

    $this->patchJson("/api/v1/letters/{$letter->id}", ['title' => 'new title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'new title');

    $this->patchJson("/api/v1/letters/{$letter->id}", ['body' => docBody('palabra unica aqui')])
        ->assertOk()
        ->assertJsonPath('data.word_count', 3);
});

it('refuses to edit or delete a locked (sent) letter', function (): void {
    $letter = Letter::factory()->locked()->create();
    Sanctum::actingAs($letter->author);

    $this->patchJson("/api/v1/letters/{$letter->id}", ['title' => 'x'])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'LETTER_LOCKED');

    $this->deleteJson("/api/v1/letters/{$letter->id}")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'LETTER_LOCKED');
});

it('soft-deletes a draft', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs($letter->author);

    $this->deleteJson("/api/v1/letters/{$letter->id}")->assertNoContent();
    expect(Letter::withTrashed()->find($letter->id)->trashed())->toBeTrue();
});

it('cannot update another user letter (404, no existence leak)', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson("/api/v1/letters/{$letter->id}", ['title' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/letters/{$letter->id}")->assertNotFound();
});
