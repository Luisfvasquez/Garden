<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\FeatureFlag;
use App\Models\LetterDelivery;
use App\Models\LetterSchedule;
use App\Models\PublicPost;
use App\Models\User;

it('seeds a coherent demo scenario', function (): void {
    $this->artisan('evergarden:seed-demo')->assertSuccessful();

    expect(User::where('email', 'like', '%@demo.evergarden.test')->count())->toBe(6)
        ->and(LetterDelivery::where('status', DeliveryStatus::Held)->count())->toBe(1)
        ->and(LetterSchedule::count())->toBe(1)
        ->and(PublicPost::count())->toBe(3)
        ->and(PublicPost::where('consent_status', 'pending')->count())->toBe(1)
        ->and(FeatureFlag::where('enabled', false)->count())->toBe(0);
});

it('is idempotent — a second run replaces the demo users instead of piling up', function (): void {
    $this->artisan('evergarden:seed-demo')->assertSuccessful();
    $this->artisan('evergarden:seed-demo')->assertSuccessful();

    expect(User::where('email', 'like', '%@demo.evergarden.test')->count())->toBe(6);
});

it('refuses to run in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('evergarden:seed-demo')->assertFailed();

    expect(User::where('email', 'like', '%@demo.evergarden.test')->count())->toBe(0);
});
