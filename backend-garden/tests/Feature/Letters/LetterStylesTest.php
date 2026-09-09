<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('serves the style catalogue to an authenticated user', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/letters/styles')->assertOk();

    $response->assertJsonStructure([
        'data' => ['papers', 'fonts', 'inks', 'seals', 'sigils', 'stamps', 'borders'],
    ]);

    $firstPaper = $response->json('data.papers.0');
    expect($firstPaper)->toHaveKeys(['key', 'name', 'locked'])
        ->and($firstPaper['locked'])->toBeFalse();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/letters/styles')->assertUnauthorized();
});

it('does not collide with the {letter} route', function (): void {
    Sanctum::actingAs(User::factory()->create());

    // "styles" must resolve to the catalogue, not be treated as a letter id.
    $this->getJson('/api/v1/letters/styles')->assertOk()->assertJsonStructure(['data' => ['papers']]);
});
