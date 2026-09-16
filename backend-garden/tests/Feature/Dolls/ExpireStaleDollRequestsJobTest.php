<?php

declare(strict_types=1);

use App\Jobs\Dolls\ExpireStaleDollRequestsJob;
use App\Models\DollRequest;

it('expires only pending requests past their deadline', function (): void {
    $stale = DollRequest::factory()->create(['expires_at' => now()->subMinute()]);
    $fresh = DollRequest::factory()->create(['expires_at' => now()->addHour()]);
    $alreadyAccepted = DollRequest::factory()->accepted()->create();

    app(ExpireStaleDollRequestsJob::class)->handle();

    expect($stale->fresh()->status->value)->toBe('expired')
        ->and($stale->fresh()->expires_at)->toBeNull()
        ->and($fresh->fresh()->status->value)->toBe('pending')
        ->and($alreadyAccepted->fresh()->status->value)->toBe('accepted');
});

it('is a no-op when nothing is stale', function (): void {
    DollRequest::factory()->create(['expires_at' => now()->addHour()]);

    app(ExpireStaleDollRequestsJob::class)->handle();

    expect(DollRequest::where('status', 'expired')->count())->toBe(0);
});
