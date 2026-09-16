<?php

declare(strict_types=1);

use App\Models\DollProfile;
use App\Models\FeatureFlag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => true]);
});

it('404s the directory when the feature flag is off', function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => false]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/dolls')->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/dolls')->assertUnauthorized();
});

it('lists only verified dolls', function (): void {
    DollProfile::factory()->verified()->count(2)->create();
    DollProfile::factory()->create(); // unverified — never listed

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/dolls')->assertOk()->assertJsonCount(2, 'data');
});

it('filters by specialty and language', function (): void {
    DollProfile::factory()->verified()->create(['specialties' => ['duelo'], 'languages' => ['es']]);
    DollProfile::factory()->verified()->create(['specialties' => ['negocios'], 'languages' => ['en']]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/dolls?specialty=duelo')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/dolls?language=en')->assertOk()->assertJsonCount(1, 'data');
});

it('filters by availability', function (): void {
    DollProfile::factory()->verified()->create(['is_available' => true]);
    DollProfile::factory()->verified()->unavailable()->create();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/dolls?available=1')->assertOk()->assertJsonCount(1, 'data');
});

it('never exposes the email in the directory', function (): void {
    $profile = DollProfile::factory()->verified()->create();
    Sanctum::actingAs(User::factory()->create());

    $body = $this->getJson("/api/v1/dolls/{$profile->user->postal_handle}")->assertOk()->json('data');

    expect($body)->not->toHaveKey('email')
        ->and($body['doll'])->not->toHaveKey('email');
});

it('hides verified_at and max_concurrent_requests from strangers', function (): void {
    $profile = DollProfile::factory()->verified()->create();
    Sanctum::actingAs(User::factory()->create());

    $body = $this->getJson("/api/v1/dolls/{$profile->user->postal_handle}")->assertOk()->json('data');

    expect($body)->not->toHaveKey('verified_at')
        ->and($body)->not->toHaveKey('max_concurrent_requests');
});

it('404s an unverified profile to a stranger but shows it to its owner', function (): void {
    $profile = DollProfile::factory()->create(); // unverified

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/v1/dolls/{$profile->user->postal_handle}")->assertNotFound();

    Sanctum::actingAs($profile->user);
    $this->getJson("/api/v1/dolls/{$profile->user->postal_handle}")
        ->assertOk()
        ->assertJsonPath('data.verified_at', null);
});
