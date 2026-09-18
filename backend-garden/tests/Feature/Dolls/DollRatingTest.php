<?php

declare(strict_types=1);

use App\Jobs\Dolls\RecalculateDollRatingsJob;
use App\Models\DollProfile;
use App\Models\DollRequest;
use App\Models\FeatureFlag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'dolls'], ['enabled' => true]);
});

function completedRequest(?User $client = null, ?DollProfile $profile = null): array
{
    $client ??= User::factory()->create(['email_verified_at' => now()]);
    $profile ??= DollProfile::factory()->verified()->create();

    $request = DollRequest::factory()->completed()->create([
        'client_id' => $client->id,
        'doll_id' => $profile->user_id,
    ]);

    return [$request, $client, $profile];
}

// --- Rating an individual request -------------------------------------------

it('lets the client rate a completed request', function (): void {
    [$request, $client] = completedRequest();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", [
        'rating' => 5,
        'comment' => 'Entendió lo que yo no sabía decir.',
    ])
        ->assertOk()
        ->assertJsonPath('data.client_rating', 5)
        ->assertJsonPath('data.client_rating_comment', 'Entendió lo que yo no sabía decir.');

    expect($request->fresh()->rated_at)->not->toBeNull();
});

it('refuses a second rating', function (): void {
    [$request, $client] = completedRequest();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 5])->assertOk();
    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 1])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'ALREADY_RATED');

    expect($request->fresh()->client_rating)->toBe(5);
});

it('refuses to rate a request that is not completed', function (): void {
    $client = User::factory()->create(['email_verified_at' => now()]);
    $profile = DollProfile::factory()->verified()->create();
    $request = DollRequest::factory()->inProgress()->create([
        'client_id' => $client->id,
        'doll_id' => $profile->user_id,
    ]);

    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 5])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('refuses a rating from the doll, and 404s a stranger', function (): void {
    [$request, , $profile] = completedRequest();

    Sanctum::actingAs($profile->user);
    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 5])->assertNotFound();

    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 5])->assertNotFound();

    expect($request->fresh()->rated_at)->toBeNull();
});

it('rejects a rating outside 1..5', function (): void {
    [$request, $client] = completedRequest();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 0])->assertStatus(422);
    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 6])->assertStatus(422);
});

// --- The aggregate is derived, never incremented -----------------------------

it('does not touch the profile aggregate when a request is rated', function (): void {
    [$request, $client, $profile] = completedRequest();
    Sanctum::actingAs($client);

    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 5])->assertOk();

    // The job owns the aggregate; the endpoint must not pre-empt it.
    expect((float) $profile->fresh()->rating_avg)->toBe(0.0)
        ->and($profile->fresh()->rating_count)->toBe(0);
});

it('rolls ratings up into the profile', function (): void {
    $profile = DollProfile::factory()->verified()->create();

    foreach ([5, 4, 3] as $score) {
        [$request, $client] = completedRequest(profile: $profile);
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => $score])->assertOk();
    }

    (new RecalculateDollRatingsJob)->handle();

    $profile->refresh();
    expect((float) $profile->rating_avg)->toBe(4.0)
        ->and($profile->rating_count)->toBe(3)
        ->and($profile->completed_requests_count)->toBe(3);
});

it('counts completed requests even when they were never rated', function (): void {
    $profile = DollProfile::factory()->verified()->create();
    completedRequest(profile: $profile);
    completedRequest(profile: $profile);

    (new RecalculateDollRatingsJob)->handle();

    $profile->refresh();
    expect($profile->completed_requests_count)->toBe(2)
        ->and($profile->rating_count)->toBe(0)
        ->and((float) $profile->rating_avg)->toBe(0.0);
});

it('recomputes from scratch, so a removed rating lowers the average again', function (): void {
    $profile = DollProfile::factory()->verified()->create();

    foreach ([5, 1] as $score) {
        [$request, $client] = completedRequest(profile: $profile);
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => $score])->assertOk();
    }

    (new RecalculateDollRatingsJob)->handle();
    expect((float) $profile->fresh()->rating_avg)->toBe(3.0);

    DollRequest::query()->where('client_rating', 1)->delete();
    (new RecalculateDollRatingsJob)->handle();

    expect((float) $profile->fresh()->rating_avg)->toBe(5.0)
        ->and($profile->fresh()->rating_count)->toBe(1);
});

// --- Not enough ratings to show a number ------------------------------------

it('hides the average until there are enough ratings', function (): void {
    config(['dolls.min_ratings_to_display' => 3]);
    $profile = DollProfile::factory()->verified()->create();

    foreach ([5, 5] as $score) {
        [$request, $client] = completedRequest(profile: $profile);
        Sanctum::actingAs($client);
        $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => $score])->assertOk();
    }
    (new RecalculateDollRatingsJob)->handle();

    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
    $this->getJson("/api/v1/dolls/{$profile->user->postal_handle}")
        ->assertOk()
        ->assertJsonPath('data.rating_avg', null)
        ->assertJsonPath('data.rating_count', 2);

    // A third rating crosses the threshold.
    [$request, $client] = completedRequest(profile: $profile);
    Sanctum::actingAs($client);
    $this->postJson("/api/v1/doll-requests/{$request->id}/rate", ['rating' => 5])->assertOk();
    (new RecalculateDollRatingsJob)->handle();

    Sanctum::actingAs(User::factory()->create(['email_verified_at' => now()]));
    $this->getJson("/api/v1/dolls/{$profile->user->postal_handle}")
        ->assertOk()
        // json_encode drops the zero fraction: 5.0 goes out as 5. In JS both are `number`.
        ->assertJsonPath('data.rating_avg', 5);
});
