<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\Auth\DeviceController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LetterController;
use App\Http\Controllers\Api\V1\LetterStyleController;
use App\Http\Controllers\Api\V1\MailboxController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeSettingsController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostalHandleController;
use App\Http\Controllers\Api\V1\PublicUserController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SendLetterController;
use App\Http\Controllers\Api\V1\SupportResourceController;
use Illuminate\Support\Facades\Route;

/*
 * API v1. Contract: ../../docs/api/. Every write endpoint carries a rate limit.
 */

// --- Public ---------------------------------------------------------------
Route::middleware('throttle:api')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('features', FeatureFlagController::class)->name('features');
    Route::get('support-resources', [SupportResourceController::class, 'index'])->name('support-resources.index');
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

    // --- Letters (composición) — contrato: docs/api/cartas.md ---
    Route::get('letters/styles', [LetterStyleController::class, 'index'])->name('letters.styles');
    Route::get('letters/{letter}/preview', [LetterController::class, 'preview'])->name('letters.preview');
    Route::post('letters/{letter}/send', SendLetterController::class)
        ->middleware(['verified', 'throttle:send-letter', 'idempotency'])
        ->name('letters.send');
    Route::post('letters/{letter}/attachments', [AttachmentController::class, 'store'])->name('letters.attachments.store');
    Route::delete('letters/{letter}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('letters.attachments.destroy');
    Route::apiResource('letters', LetterController::class);

    // --- Envíos: bandeja de salida, seguimiento y cancelación ---
    Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::get('deliveries/{delivery}/tracking', [DeliveryController::class, 'tracking'])->name('deliveries.tracking');
    Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');

    // --- Buzón del destinatario ---
    Route::get('mailbox', [MailboxController::class, 'index'])->name('mailbox.index');
    Route::get('mailbox/unread-count', [MailboxController::class, 'unreadCount'])->name('mailbox.unread-count');
    Route::get('mailbox/{delivery}', [MailboxController::class, 'show'])->name('mailbox.show');
    Route::post('mailbox/{delivery}/open', [MailboxController::class, 'open'])->name('mailbox.open');
    Route::post('mailbox/{delivery}/archive', [MailboxController::class, 'archive'])->name('mailbox.archive');
    Route::post('mailbox/{delivery}/favorite', [MailboxController::class, 'favorite'])->name('mailbox.favorite');
    Route::post('mailbox/{delivery}/reply', [MailboxController::class, 'reply'])->name('mailbox.reply');

    // --- Bloqueos y reportes — contrato: docs/api/comunidad-notificaciones.md ---
    Route::get('blocks', [BlockController::class, 'index'])->name('blocks.index');
    Route::post('blocks', [BlockController::class, 'store'])->name('blocks.store');
    Route::delete('blocks/{userId}', [BlockController::class, 'destroy'])->name('blocks.destroy');

    Route::post('reports', [ReportController::class, 'store'])
        ->middleware('throttle:report')
        ->name('reports.store');

    // --- Notificaciones ---
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});
