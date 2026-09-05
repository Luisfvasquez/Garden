<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the minimal public profile by exact handle', function (): void {
    $target = User::factory()->create([
        'name' => 'Hana',
        'pen_name' => null,
        'country_code' => 'JP',
        'bio' => 'letters from Kyoto',
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/users/{$target->postal_handle}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'postal_handle' => $target->postal_handle,
            'display_name' => 'Hana',
            'avatar_url' => null,
            'bio' => 'letters from Kyoto',
            'country_code' => 'JP',
            'member_since' => $target->created_at->format('Y-m'),
            'accepts_random_letters' => false,
            'is_blocked_by_me' => false,
        ]]);
});

it('prefers the pen name as display name and never leaks email', function (): void {
    $target = User::factory()->create(['pen_name' => 'ghostwriter', 'email' => 'real@example.com']);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/users/{$target->postal_handle}")
        ->assertOk()
        ->assertJsonPath('data.display_name', 'ghostwriter')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.id');
});

it('404s for an unknown handle', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/users/nobody-0000')
        ->assertNotFound()
        ->assertJsonPath('error_code', 'NOT_FOUND');
});

it('404s for a non-active account without revealing it exists', function (): void {
    $suspended = User::factory()->create(['status' => UserStatus::Suspended]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/users/{$suspended->postal_handle}")->assertNotFound();
});

it('requires authentication', function (): void {
    $target = User::factory()->create();

    $this->getJson("/api/v1/users/{$target->postal_handle}")->assertUnauthorized();
});
