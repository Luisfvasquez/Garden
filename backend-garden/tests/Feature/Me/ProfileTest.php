<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the private profile including email', function (): void {
    $user = User::factory()->create([
        'name' => 'Violet',
        'email' => 'violet@example.com',
        'country_code' => 'US',
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'violet@example.com')
        ->assertJsonPath('data.postal_handle', $user->postal_handle)
        ->assertJsonPath('data.has_doll_profile', false)
        ->assertJsonPath('data.unread_mailbox_count', 0)
        ->assertJsonStructure(['data' => ['id', 'name', 'pen_name', 'email', 'role', 'status', 'email_verified', 'created_at']]);
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('error_code', 'UNAUTHENTICATED');
});

it('updates editable profile fields', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', [
        'name' => 'Violet E.',
        'pen_name' => 'the-ghostwriter',
        'bio' => 'I write letters.',
        'country_code' => 'jp',
        'timezone' => 'Asia/Tokyo',
        'locale' => 'en',
    ])
        ->assertOk()
        ->assertJsonPath('data.pen_name', 'the-ghostwriter')
        ->assertJsonPath('data.country_code', 'JP')
        ->assertJsonPath('data.timezone', 'Asia/Tokyo');
});

it('rejects a pen_name already taken by someone else', function (): void {
    User::factory()->create(['pen_name' => 'taken']);
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me', ['pen_name' => 'taken'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pen_name');
});

it('allows keeping your own pen_name on update', function (): void {
    $user = User::factory()->create(['pen_name' => 'mine']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['pen_name' => 'mine', 'bio' => 'unchanged handle'])
        ->assertOk()
        ->assertJsonPath('data.pen_name', 'mine');
});
