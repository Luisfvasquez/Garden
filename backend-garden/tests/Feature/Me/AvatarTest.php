<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('stores the avatar and returns its url', function (): void {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/me/avatar', [
        'avatar' => UploadedFile::fake()->image('me.png', 512, 512),
    ])->assertOk();

    expect($response->json('data.avatar_url'))->toBeString()->not->toBeEmpty();
    expect(Storage::disk('public')->allFiles('avatars'))->toHaveCount(1);
});

it('replaces and deletes the previous avatar', function (): void {
    Storage::fake('public');
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('a.png')]);
    $first = $user->fresh()->avatar_path;

    $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('b.png')]);

    Storage::disk('public')->assertMissing($first);
    expect(Storage::disk('public')->allFiles('avatars'))->toHaveCount(1);
});

it('rejects a non-image and an oversize file', function (): void {
    Storage::fake('public');
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('avatar');

    $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('huge.jpg')->size(6000)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('avatar');
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/me/avatar')->assertUnauthorized();
});
