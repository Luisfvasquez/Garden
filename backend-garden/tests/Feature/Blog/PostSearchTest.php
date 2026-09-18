<?php

declare(strict_types=1);

use App\Models\FeatureFlag;
use App\Models\PublicPost;
use App\Models\User;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'blog'], ['enabled' => true]);
});

function publishedPost(string $title, string $body): PublicPost
{
    return PublicPost::factory()->create([  // la factory ya nace publicada
        'author_id' => User::factory()->create()->id,
        'title' => $title,
        'body_plain' => $body,
    ]);
}

// --- Finding things -----------------------------------------------------------

it('finds a post by a word in its title', function (): void {
    publishedPost('Cartas a mi padre', 'Un texto cualquiera.');
    publishedPost('Recetas de la abuela', 'Otro texto distinto.');

    $this->getJson('/api/v1/posts?q=padre')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Cartas a mi padre');
});

it('finds a post by a word in its body', function (): void {
    publishedPost('Un título neutro', 'Hablaba mucho del faro de su pueblo.');

    $this->getJson('/api/v1/posts?q=faro')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('stems Spanish, so a plural finds the singular', function (): void {
    publishedPost('Una carta sin enviar', 'La guardé en un cajón.');

    // This is the whole reason the vector moved off `simple`: with no stemming,
    // "cartas" would never have matched "carta".
    $this->getJson('/api/v1/posts?q=cartas')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('stems verbs too', function (): void {
    publishedPost('Sobre el oficio', 'Llevo años escribiendo a desconocidos.');

    $this->getJson('/api/v1/posts?q=escribir')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('ranks a title match above a body mention', function (): void {
    publishedPost('Un día cualquiera', 'Mi padre nunca supo lo que pensaba.');
    publishedPost('Padre', 'Texto sin relación con nada.');

    $this->getJson('/api/v1/posts?q=padre')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Padre');
});

// --- What it must not return --------------------------------------------------

it('never returns an unpublished post', function (): void {
    PublicPost::factory()->create([
        'author_id' => User::factory()->create()->id,
        'title' => 'Borrador sobre el faro',
        'body_plain' => 'faro',
        'published_at' => null,
    ]);

    $this->getJson('/api/v1/posts?q=faro')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns nothing for terms that match nothing', function (): void {
    publishedPost('Cartas a mi padre', 'Un texto cualquiera.');

    $this->getJson('/api/v1/posts?q=submarino')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// --- Anything a person might type --------------------------------------------

it('survives operator characters instead of turning a typo into a 500', function (string $terms): void {
    publishedPost('Cartas a mi padre', 'Un texto cualquiera.');

    // `to_tsquery` raises a syntax error on all of these. `websearch_to_tsquery`
    // is the one that treats them as what a person meant.
    $this->getJson('/api/v1/posts?q='.urlencode($terms))->assertOk();
})->with(['cartas &', '& | !', '(((', 'a:b:c', '"sin cerrar', '<script>alert(1)</script>', "o'brien"]);

it('supports an exact phrase in quotes', function (): void {
    publishedPost('Cartas a mi padre', 'Le escribí una carta muy larga.');
    publishedPost('Otro asunto', 'Una carta corta para mi madre.');

    $this->getJson('/api/v1/posts?q='.urlencode('"carta muy larga"'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Cartas a mi padre');
});

it('supports excluding a term with a minus', function (): void {
    publishedPost('Cartas a mi padre', 'Un texto sobre el faro.');
    publishedPost('Cartas a mi madre', 'Un texto sobre el mar.');

    $this->getJson('/api/v1/posts?q='.urlencode('cartas -faro'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Cartas a mi madre');
});

it('treats a blank q as no search at all', function (): void {
    publishedPost('Cartas a mi padre', 'Un texto cualquiera.');
    publishedPost('Recetas de la abuela', 'Otro texto distinto.');

    $this->getJson('/api/v1/posts?q=%20')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// --- It still composes with everything else ----------------------------------

it('combines with the type filter', function (): void {
    publishedPost('Cartas a mi padre', 'texto')->update(['type' => 'poem']);
    publishedPost('Cartas a mi madre', 'texto')->update(['type' => 'reflection']);

    $this->getJson('/api/v1/posts?q=cartas&type=poem')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Cartas a mi padre');
});

it('paginates search results by cursor without repeating or dropping rows', function (): void {
    foreach (range(1, 7) as $i) {
        publishedPost("Carta número {$i}", 'Todas hablan de lo mismo.');
    }

    $seen = [];
    $url = '/api/v1/posts?q=carta&per_page=3';

    // The rank is SELECTed as a column precisely so this works; a raw ORDER BY
    // would have made the cursor unbuildable.
    for ($page = 0; $page < 5; $page++) {
        $response = $this->getJson($url)->assertOk();
        $seen = [...$seen, ...collect($response->json('data'))->pluck('id')->all()];

        $cursor = $response->json('meta.next_cursor');
        if ($cursor === null) {
            break;
        }
        $url = '/api/v1/posts?q=carta&per_page=3&cursor='.urlencode($cursor);
    }

    expect($seen)->toHaveCount(7)
        ->and(array_unique($seen))->toHaveCount(7);
});

// --- The index keeps itself current ------------------------------------------

it('reindexes when a post is edited', function (): void {
    $post = publishedPost('Un título neutro', 'Hablaba del faro.');

    $this->getJson('/api/v1/posts?q=montaña')->assertJsonCount(0, 'data');

    $post->update(['body_plain' => 'Ahora habla de la montaña.']);

    $this->getJson('/api/v1/posts?q=montaña')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
