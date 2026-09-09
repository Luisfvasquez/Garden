<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Enums\ReportableType;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Models\ModerationAction;
use App\Models\Report;
use App\Models\User;

it('cancels pending random deliveries between a pair when one blocks the other', function (): void {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $pending = LetterDelivery::factory()->create([
        'letter_id' => Letter::factory()->for($a, 'author')->create()->id,
        'sender_id' => $a->id,
        'recipient_id' => $b->id,
        'delivery_mode' => 'random',
        'status' => DeliveryStatus::Queued,
    ]);
    $reverse = LetterDelivery::factory()->create([
        'letter_id' => Letter::factory()->for($b, 'author')->create()->id,
        'sender_id' => $b->id,
        'recipient_id' => $a->id,
        'delivery_mode' => 'random',
        'status' => DeliveryStatus::Held,
    ]);

    $this->actingAs($a);
    $this->postJson('/api/v1/blocks', ['user_id' => $b->id])->assertCreated();

    expect($pending->fresh()->status)->toBe(DeliveryStatus::Cancelled)
        ->and($reverse->fresh()->status)->toBe(DeliveryStatus::Cancelled);
});

it('auto-restricts a sender after two confirmed reports on their random letters', function (): void {
    $offender = User::factory()->create();

    $deliveries = LetterDelivery::factory()->count(2)->create([
        'sender_id' => $offender->id,
        'delivery_mode' => 'random',
    ]);

    $reports = $deliveries->map(fn ($d) => Report::create([
        'reporter_id' => User::factory()->create()->id,
        'reportable_type' => ReportableType::LetterDelivery,
        'reportable_id' => $d->id,
        'category' => 'harassment',
        'severity' => 'medium',
    ]));

    $reports[0]->markActioned();
    expect(ModerationAction::restrictsRandom($offender))->toBeFalse();

    $reports[1]->markActioned();
    expect(ModerationAction::restrictsRandom($offender->fresh()))->toBeTrue();
});

it('does not restrict on confirmed reports about non-random letters', function (): void {
    $offender = User::factory()->create();
    $deliveries = LetterDelivery::factory()->count(2)->create([
        'sender_id' => $offender->id,
        'delivery_mode' => 'direct',
    ]);

    $deliveries->each(fn ($d) => Report::create([
        'reporter_id' => User::factory()->create()->id,
        'reportable_type' => ReportableType::LetterDelivery,
        'reportable_id' => $d->id,
        'category' => 'harassment',
        'severity' => 'medium',
    ])->markActioned());

    expect(ModerationAction::restrictsRandom($offender))->toBeFalse();
});
