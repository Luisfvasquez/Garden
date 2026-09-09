<?php

declare(strict_types=1);

use App\Models\Letter;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('resolves style keys against the catalogue for a full-page preview', function (): void {
    $letter = Letter::factory()->create([
        'style' => ['paper' => 'parchment', 'ink' => 'sepia', 'seal' => ['color' => 'wax_burgundy', 'sigil' => 'violet']],
    ]);
    Sanctum::actingAs($letter->author);

    $response = $this->getJson("/api/v1/letters/{$letter->id}/preview")->assertOk();

    $response
        ->assertJsonPath('data.letter.id', $letter->id)
        ->assertJsonPath('data.resolved_style.paper.name', 'Pergamino')
        ->assertJsonPath('data.resolved_style.ink.hex', '#6b4423')
        ->assertJsonPath('data.resolved_style.seal.color.name', 'Lacre burdeos');
});

it('404s previewing another user letter', function (): void {
    $letter = Letter::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/letters/{$letter->id}/preview")->assertNotFound();
});
