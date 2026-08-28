<?php

declare(strict_types=1);

use App\Models\FeatureFlag;

it('exposes the flag map publicly with unknown modules off by default', function (): void {
    FeatureFlag::query()->create(['key' => 'letters', 'enabled' => false]);
    FeatureFlag::query()->create(['key' => 'dolls', 'enabled' => true]);

    $this->getJson('/api/v1/features')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'features' => [
                    'dolls' => true,
                    'letters' => false,
                ],
            ],
        ]);
});

it('returns an empty map when no flags are registered', function (): void {
    $this->getJson('/api/v1/features')
        ->assertOk()
        ->assertJsonPath('data.features', []);
});
