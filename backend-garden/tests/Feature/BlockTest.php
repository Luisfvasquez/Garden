<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('blocks a user by postal handle and lists it', function (): void {
    $me = User::factory()->create();
    $target = User::factory()->create(['name' => 'Noisy Neighbour']);
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/blocks', ['postal_handle' => $target->postal_handle, 'reason' => 'spam'])
        ->assertCreated()
        ->assertJsonPath('data.user.postal_handle', $target->postal_handle)
        ->assertJsonPath('data.reason', 'spam');

    expect(Block::exists($me->id, $target->id))->toBeTrue();

    $this->getJson('/api/v1/blocks')->assertOk()->assertJsonCount(1, 'data');
});

it('blocks by user_id and is idempotent', function (): void {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/blocks', ['user_id' => $target->id])->assertCreated();
    $this->postJson('/api/v1/blocks', ['user_id' => $target->id, 'reason' => 'updated'])->assertCreated();

    expect(Block::where('blocker_id', $me->id)->where('blocked_id', $target->id)->count())->toBe(1);
});

it('refuses to block yourself', function (): void {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/blocks', ['user_id' => $me->id])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'CANNOT_BLOCK_SELF');
});

it('404s blocking someone who does not exist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/blocks', ['postal_handle' => 'ghost-0000'])->assertNotFound();
});

it('requires either a user_id or a postal_handle', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/blocks', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id', 'postal_handle']);
});

it('unblocks and is idempotent', function (): void {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Block::create(['blocker_id' => $me->id, 'blocked_id' => $target->id]);
    Sanctum::actingAs($me);

    $this->deleteJson("/api/v1/blocks/{$target->id}")->assertNoContent();
    $this->deleteJson("/api/v1/blocks/{$target->id}")->assertNoContent(); // already gone

    expect(Block::exists($me->id, $target->id))->toBeFalse();
});

it('only lists my own blocks', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Block::create(['blocker_id' => $me->id, 'blocked_id' => User::factory()->create()->id]);
    Block::create(['blocker_id' => $other->id, 'blocked_id' => User::factory()->create()->id]);
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/blocks')->assertOk()->assertJsonCount(1, 'data');
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/blocks')->assertUnauthorized();
});
