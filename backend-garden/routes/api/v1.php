<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\DeviceController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeSettingsController;
use App\Http\Controllers\Api\V1\PostalHandleController;
use App\Http\Controllers\Api\V1\PublicUserController;
use Illuminate\Support\Facades\Route;

/*
 * API v1. Contract: ../../docs/api/. Every write endpoint carries a rate limit.
 */

// --- Public ---------------------------------------------------------------
Route::middleware('throttle:api')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('features', FeatureFlagController::class)->name('features');
});

// --- Auth (bucket: 5/min per IP) ----------------------------------------
Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function (): void {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', LoginController::class)->name('login');
    Route::post('token', TokenController::class)->name('token');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->name('forgot-password');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('reset-password');

    Route::post('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed:relative')
        ->name('email.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])->name('email.resend');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::delete('devices/{id}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });
});

// --- Authenticated (bucket: 60/min per user) --------------------------------
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::get('me', [MeController::class, 'show'])->name('me.show');
    Route::patch('me', [MeController::class, 'update'])->name('me.update');
    Route::post('me/avatar', [AvatarController::class, 'store'])->name('me.avatar');
    Route::get('me/settings', [MeSettingsController::class, 'show'])->name('me.settings.show');
    Route::patch('me/settings', [MeSettingsController::class, 'update'])->name('me.settings.update');
    Route::post('me/postal-handle/rotate', [PostalHandleController::class, 'rotate'])->name('me.postal-handle.rotate');
    Route::post('me/deactivate', [MeController::class, 'deactivate'])->name('me.deactivate');
    Route::delete('me', [MeController::class, 'destroy'])->name('me.destroy');

    Route::get('users/{postal_handle}', [PublicUserController::class, 'show'])->name('users.show');
});
