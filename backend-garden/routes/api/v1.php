<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
 * API v1. Contract: ../../docs/api/. All write endpoints must carry a rate limit.
 */

Route::middleware('throttle:api')->group(function (): void {
    // Public
    Route::get('health', HealthController::class)->name('health');
    Route::get('features', FeatureFlagController::class)->name('features');
});
