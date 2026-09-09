<?php

declare(strict_types=1);

use App\Models\FeatureFlag;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\LetterSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'schedules'], ['enabled' => true]);
    CarbonImmutable::setTestNow('2026-09-09T12:00:00Z');
    $this->travelTo('2026-09-09T12:00:00Z');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    $this->travelBack();
});

function schedulePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Cumpleaños de Ann',
        'recipient' => ['postal_handle' => 'placeholder'],
        'recurrence_type' => 'yearly',
        'anchor_date' => '2027-04-12',
        'local_time' => '09:00',
        'timezone' => 'America/New_York',
        'occurrences_total' => 10,
        'letter_id' => null,
        'leap_day_policy' => 'feb_28',
    ], $overrides);
}

it('404s every schedule route when the feature flag is off', function (): void {
    FeatureFlag::query()->updateOrCreate(['key' => 'schedules'], ['enabled' => false]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/schedules')->assertNotFound();
    $this->postJson('/api/v1/schedules', schedulePayload())->assertNotFound();
});

it('requires authentication', function (): void {
    $this->getJson('/api/v1/schedules')->assertUnauthorized();
});

it('creates a schedule and echoes the frozen timezone', function (): void {
    $me = User::factory()->create();
    $ann = User::factory()->create(['name' => 'Ann']);
    Sanctum::actingAs($me);

    $response = $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $ann->postal_handle],
    ]))->assertCreated();

    $response
        ->assertJsonPath('data.recurrence_type', 'yearly')
        ->assertJsonPath('data.timezone', 'America/New_York')
        ->assertJsonPath('data.recipient.postal_handle', $ann->postal_handle)
        ->assertJsonPath('data.status', 'active');

    expect(LetterSchedule::where('user_id', $me->id)->count())->toBe(1);
});

it('blocks scheduling a letter to yourself', function (): void {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $me->postal_handle],
    ]))->assertStatus(422)->assertJsonPath('error_code', 'INVALID_TARGET');
});

it('422s an unknown recipient handle', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => 'nobody-0000'],
    ]))->assertStatus(422)->assertJsonPath('error_code', 'RECIPIENT_NOT_FOUND');
});

it('422s a stepped recurrence with no occurrences_total', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $recipient = User::factory()->create();

    $payload = schedulePayload(['recipient' => ['postal_handle' => $recipient->postal_handle]]);
    unset($payload['occurrences_total']);

    $this->postJson('/api/v1/schedules', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('occurrences_total');
});

it('needs a verified email to create', function (): void {
    $me = User::factory()->unverified()->create();
    $recipient = User::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $recipient->postal_handle],
    ]))->assertStatus(403)->assertJsonPath('error_code', 'EMAIL_NOT_VERIFIED');
});

it('lists only my schedules', function (): void {
    $me = User::factory()->create();
    LetterSchedule::factory()->for($me, 'user')->count(2)->create();
    LetterSchedule::factory()->create(); // someone else's
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(2, 'data');
});

it("hides another user's schedule behind a 404", function (): void {
    $mine = LetterSchedule::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/schedules/{$mine->id}")->assertNotFound();
    $this->patchJson("/api/v1/schedules/{$mine->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/schedules/{$mine->id}")->assertNotFound();
});

it('pauses and resumes', function (): void {
    $me = User::factory()->create();
    $schedule = LetterSchedule::factory()->for($me, 'user')->create();
    Sanctum::actingAs($me);

    $this->postJson("/api/v1/schedules/{$schedule->id}/pause")->assertOk()->assertJsonPath('data.status', 'paused');
    $this->postJson("/api/v1/schedules/{$schedule->id}/resume")->assertOk()->assertJsonPath('data.status', 'active');
});

it('returns a timeline that merges virtual and materialised occurrences', function (): void {
    $me = User::factory()->create();
    $recipient = User::factory()->create();
    $letter = Letter::factory()->for($me, 'author')->create(['title' => 'Doce años']);
    Sanctum::actingAs($me);

    // Anchor 20 days out → the first occurrence is inside the 90-day horizon and
    // materialises immediately because the schedule carries a default letter.
    $schedule = $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $recipient->postal_handle],
        'recurrence_type' => 'yearly',
        'anchor_date' => now()->addDays(20)->toDateString(),
        'occurrences_total' => 3,
        'letter_id' => $letter->id,
    ]))->json('data.id');

    $response = $this->getJson("/api/v1/schedules/{$schedule}/occurrences")->assertOk();

    $response
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.filled', 3)
        ->assertJsonPath('data.0.status', 'queued')
        ->assertJsonPath('data.0.letter.title', 'Doce años')
        ->assertJsonPath('data.2.status', 'pending'); // year 3, beyond the horizon

    expect($response->json('data.0.delivery_id'))->not->toBeNull()
        ->and($response->json('data.2.delivery_id'))->toBeNull();
});

it('assigns a letter to one occurrence date', function (): void {
    $me = User::factory()->create();
    $recipient = User::factory()->create();
    $letter = Letter::factory()->for($me, 'author')->create();
    Sanctum::actingAs($me);

    $schedule = $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $recipient->postal_handle],
        'anchor_date' => '2027-04-12',
        'occurrences_total' => 3,
    ]))->json('data.id');

    $this->putJson("/api/v1/schedules/{$schedule}/occurrences/2028-04-12/letter", ['letter_id' => $letter->id])
        ->assertOk()
        ->assertJsonPath('data.letter_id', $letter->id);

    $this->getJson("/api/v1/schedules/{$schedule}/occurrences")
        ->assertJsonPath('data.1.status', 'pending')
        ->assertJsonPath('data.1.letter.id', $letter->id);
});

it('404s assigning a letter to a date that is not an occurrence', function (): void {
    $me = User::factory()->create();
    $recipient = User::factory()->create();
    $letter = Letter::factory()->for($me, 'author')->create();
    Sanctum::actingAs($me);

    $schedule = $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $recipient->postal_handle],
        'anchor_date' => '2027-04-12',
        'occurrences_total' => 2,
    ]))->json('data.id');

    $this->putJson("/api/v1/schedules/{$schedule}/occurrences/2027-06-01/letter", ['letter_id' => $letter->id])
        ->assertNotFound();
});

it('409s reassigning a letter to an already materialised occurrence', function (): void {
    $me = User::factory()->create();
    $recipient = User::factory()->create();
    $a = Letter::factory()->for($me, 'author')->create();
    $b = Letter::factory()->for($me, 'author')->create();
    Sanctum::actingAs($me);

    $schedule = $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $recipient->postal_handle],
        'recurrence_type' => 'once',
        'anchor_date' => now()->addDays(10)->toDateString(),
        'letter_id' => $a->id,
    ]))->json('data.id');

    $date = now()->addDays(10)->toDateString();

    $this->putJson("/api/v1/schedules/{$schedule}/occurrences/{$date}/letter", ['letter_id' => $b->id])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INVALID_STATE_TRANSITION');
});

it('deletes a schedule but keeps the deliveries it already produced', function (): void {
    $me = User::factory()->create();
    $recipient = User::factory()->create();
    $letter = Letter::factory()->for($me, 'author')->create();
    Sanctum::actingAs($me);

    $schedule = $this->postJson('/api/v1/schedules', schedulePayload([
        'recipient' => ['postal_handle' => $recipient->postal_handle],
        'recurrence_type' => 'once',
        'anchor_date' => now()->addDays(5)->toDateString(),
        'letter_id' => $letter->id,
    ]))->json('data.id');

    expect(LetterDelivery::where('schedule_id', $schedule)->count())->toBe(1);

    $this->deleteJson("/api/v1/schedules/{$schedule}")->assertNoContent();

    expect(LetterSchedule::find($schedule))->toBeNull()
        ->and(LetterDelivery::whereNull('schedule_id')->count())->toBe(1);
});
