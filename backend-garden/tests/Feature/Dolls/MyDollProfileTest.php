<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\DollProfile;
use App\Models\FeatureFlag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => true]);
});

function dollProfilePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'headline' => 'Especialista en cartas de despedida',
        'bio' => 'Diez años escuchando lo que la gente no sabe decir.',
        'specialties' => ['duelo', 'disculpa'],
        'languages' => ['es'],
        'tone_tags' => ['íntimo'],
        'rate_type' => 'free',
    ], $overrides);
}

it('404s when I have not requested the role yet', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me/doll-profile')->assertNotFound();
});

it('requests the doll role unverified — it does not touch my role', function (): void {
    $me = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/me/doll-profile', dollProfilePayload())
        ->assertCreated()
        ->assertJsonPath('data.verified_at', null)
        ->assertJsonPath('data.is_available', false);

    expect($me->fresh()->role)->toBe(UserRole::Client);
});

it('needs a verified email to request the role', function (): void {
    Sanctum::actingAs(User::factory()->unverified()->create());

    $this->postJson('/api/v1/me/doll-profile', dollProfilePayload())->assertStatus(403);
});

it('refuses a second request', function (): void {
    $me = User::factory()->create(['email_verified_at' => now()]);
    DollProfile::factory()->create(['user_id' => $me->id]);
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/me/doll-profile', dollProfilePayload())
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('lets me update my own profile but not someone else\'s', function (): void {
    $me = User::factory()->create(['email_verified_at' => now()]);
    DollProfile::factory()->create(['user_id' => $me->id, 'headline' => 'Original']);
    Sanctum::actingAs($me);

    $this->patchJson('/api/v1/me/doll-profile', ['headline' => 'Actualizado'])
        ->assertOk()
        ->assertJsonPath('data.headline', 'Actualizado');
});

it('toggles availability', function (): void {
    $me = User::factory()->create();
    DollProfile::factory()->create(['user_id' => $me->id, 'is_available' => false]);
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/me/doll-profile/availability', ['is_available' => true])
        ->assertOk()
        ->assertJsonPath('data.is_available', true);
});

it('flips the role to doll only once staff verify it', function (): void {
    $me = User::factory()->create();
    $profile = DollProfile::factory()->create(['user_id' => $me->id]);

    expect($me->fresh()->role)->toBe(UserRole::Client);

    $profile->markVerified();

    expect($me->fresh()->role)->toBe(UserRole::Doll);
    expect(DollProfile::verified()->count())->toBe(1);
});

it('demotes back to client when verification is lifted', function (): void {
    $profile = DollProfile::factory()->verified()->create();

    $profile->markUnverified();

    expect($profile->user->fresh()->role)->toBe(UserRole::Client)
        ->and($profile->fresh()->is_available)->toBeFalse();
});
