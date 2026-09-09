<?php

declare(strict_types=1);

use App\Models\SupportResource;

it('is public — no auth required', function (): void {
    SupportResource::factory()->global()->create();

    $this->getJson('/api/v1/support-resources')->assertOk();
});

it('returns the country rows plus the global fallback, most prominent first', function (): void {
    SupportResource::factory()->global()->create(['name' => 'Worldwide', 'priority' => 100]);
    SupportResource::factory()->create(['country_code' => 'ES', 'name' => 'Línea 024', 'priority' => 90]);
    SupportResource::factory()->create(['country_code' => 'ES', 'name' => 'Esperanza', 'priority' => 70]);
    SupportResource::factory()->create(['country_code' => 'MX', 'name' => 'Línea de la Vida', 'priority' => 90]);

    $response = $this->getJson('/api/v1/support-resources?country_code=es')->assertOk();

    $names = array_column($response->json('data'), 'name');

    expect($names)->toBe(['Worldwide', 'Línea 024', 'Esperanza'])
        ->and($response->json('data.0.is_global'))->toBeTrue();
});

it('without a country returns only the global rows', function (): void {
    SupportResource::factory()->global()->create(['name' => 'Worldwide']);
    SupportResource::factory()->create(['country_code' => 'ES', 'name' => 'Línea 024']);

    $response = $this->getJson('/api/v1/support-resources')->assertOk();

    expect(array_column($response->json('data'), 'name'))->toBe(['Worldwide']);
});

it('hides inactive rows', function (): void {
    SupportResource::factory()->inactive()->create(['country_code' => 'ES', 'name' => 'Retirada']);

    $response = $this->getJson('/api/v1/support-resources?country_code=ES')->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('can narrow by topic', function (): void {
    SupportResource::factory()->create(['country_code' => 'ES', 'topic' => 'self_harm', 'name' => 'Crisis']);
    SupportResource::factory()->create(['country_code' => 'ES', 'topic' => 'grief', 'name' => 'Duelo']);

    $response = $this->getJson('/api/v1/support-resources?country_code=ES&topic=self_harm')->assertOk();

    expect(array_column($response->json('data'), 'name'))->toBe(['Crisis']);
});

it('422s on a malformed country code', function (): void {
    $this->getJson('/api/v1/support-resources?country_code=SPAIN')->assertStatus(422);
});
