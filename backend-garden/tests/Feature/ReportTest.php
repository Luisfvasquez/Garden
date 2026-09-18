<?php

declare(strict_types=1);

use App\Enums\ReportSeverity;
use App\Models\LetterDelivery;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('files a report on a delivery and auto-assigns severity', function (): void {
    $delivery = LetterDelivery::factory()->delivered()->create();
    Sanctum::actingAs($delivery->recipient);

    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'letter_delivery',
        'reportable_id' => $delivery->id,
        'category' => 'harassment',
        'details' => 'insultos repetidos',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.severity', 'medium');

    expect(Report::query()->where('reportable_id', $delivery->id)->exists())->toBeTrue();
});

it('marks minor_safety and self_harm as critical and logs them', function (): void {
    Log::spy();
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'user',
        'reportable_id' => $target->id,
        'category' => 'minor_safety',
    ])->assertCreated()->assertJsonPath('data.severity', 'critical');

    Log::shouldHaveReceived('critical')->once();
    expect(Report::query()->first()->severity)->toBe(ReportSeverity::Critical);
});

it('replaces an earlier report of the same target by the same reporter', function (): void {
    $delivery = LetterDelivery::factory()->delivered()->create();
    $reporter = User::factory()->create();
    Sanctum::actingAs($reporter);

    $body = fn (string $category) => [
        'reportable_type' => 'letter_delivery',
        'reportable_id' => $delivery->id,
        'category' => $category,
    ];

    $this->postJson('/api/v1/reports', $body('spam'))->assertCreated();
    $this->postJson('/api/v1/reports', $body('hate'))->assertCreated();

    expect(Report::query()->where('reporter_id', $reporter->id)->count())->toBe(1)
        ->and(Report::query()->first()->category->value)->toBe('hate');
});

it('rejects reporting content that does not exist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'letter_delivery',
        'reportable_id' => Str::uuid()->toString(),
        'category' => 'spam',
    ])->assertNotFound();
});

// `doll_chat_message` used to be the "module not built" case here; Fase 3C
// wired it, so the miss is now an ordinary 404. Reporting inside a chat has
// its own isolation tests in tests/Feature/Dolls/DollChatTest.php.
it('404s reporting a chat message that does not exist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'doll_chat_message',
        'reportable_id' => Str::uuid()->toString(),
        'category' => 'spam',
    ])->assertNotFound();
});

it('rejects reporting yourself', function (): void {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/reports', [
        'reportable_type' => 'user',
        'reportable_id' => $me->id,
        'category' => 'other',
    ])->assertStatus(422)->assertJsonPath('error_code', 'INVALID_TARGET');
});

it('validates the payload', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/reports', ['reportable_type' => 'nope', 'category' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reportable_type', 'reportable_id', 'category']);
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/reports', [])->assertUnauthorized();
});
