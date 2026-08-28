<?php

declare(strict_types=1);

use App\Models\User;

it('builds a slugged handle with a 4 hex suffix', function (): void {
    $handle = User::generatePostalHandle('Violet Evergarden');

    expect($handle)->toMatch('/^violet-evergarden-[0-9a-f]{4}$/');
});

it('falls back to a safe base for names without latin letters', function (): void {
    expect(User::generatePostalHandle('***'))->toMatch('/^doll-[0-9a-f]{4}$/');
});

it('assigns a unique handle on create even under the same name', function (): void {
    $a = User::factory()->create(['name' => 'Hana']);
    $b = User::factory()->create(['name' => 'Hana']);

    expect($a->postal_handle)->not->toBe($b->postal_handle);
});
