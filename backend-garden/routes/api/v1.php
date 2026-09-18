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
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\DollChatController;
use App\Http\Controllers\Api\V1\DollDirectoryController;
use App\Http\Controllers\Api\V1\DollDraftController;
use App\Http\Controllers\Api\V1\DollRequestController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LetterController;
use App\Http\Controllers\Api\V1\LetterStyleController;
use App\Http\Controllers\Api\V1\MailboxController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeSettingsController;
use App\Http\Controllers\Api\V1\MyDollProfileController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostalHandleController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostReactionController;
use App\Http\Controllers\Api\V1\PublicUserController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\RandomQuotaController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SendLetterController;
use App\Http\Controllers\Api\V1\SendRandomLetterController;
use App\Http\Controllers\Api\V1\SupportResourceController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\VapidKeyController;
use Illuminate\Support\Facades\Route;

/*
 * API v1. Contract: ../../docs/api/. Every write endpoint carries a rate limit.
 */

// --- Public ---------------------------------------------------------------
Route::middleware('throttle:api')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');
    Route::get('features', FeatureFlagController::class)->name('features');
    Route::get('support-resources', [SupportResourceController::class, 'index'])->name('support-resources.index');
    Route::get('push/vapid-public-key', VapidKeyController::class)->name('push.vapid-public-key');

    // --- Blog público (lectura) — contrato: docs/api/blog.md ---
    Route::middleware('feature:blog')->group(function (): void {
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{slug}', [PostController::class, 'show'])->name('posts.show');
        Route::get('posts/{post}/comments', [CommentController::class, 'index'])->name('posts.comments.index');
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    });
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

    // --- Botella al mar — contrato: docs/api/botella-al-mar.md · ADR-0004 ---
    Route::middleware('feature:bottle_at_sea')->group(function (): void {
        Route::post('letters/{letter}/send-random', SendRandomLetterController::class)
            ->middleware(['verified', 'throttle:send-random', 'idempotency'])
            ->name('letters.send-random');
        Route::get('random/quota', RandomQuotaController::class)->name('random.quota');
        Route::post('mailbox/{delivery}/reply-anonymous', [MailboxController::class, 'replyAnonymous'])
            ->middleware(['verified', 'throttle:send-random'])
            ->name('mailbox.reply-anonymous');
        Route::post('mailbox/{delivery}/open-correspondence', [MailboxController::class, 'openCorrespondence'])
            ->name('mailbox.open-correspondence');
    });

    // --- Blog público (escritura) — contrato: docs/api/blog.md ---
    Route::middleware('feature:blog')->group(function (): void {
        Route::post('posts', [PostController::class, 'store'])
            ->middleware(['verified', 'throttle:create-post'])->name('posts.store');
        Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

        Route::post('posts/{post}/reactions', [PostReactionController::class, 'store'])->name('posts.reactions.store');
        Route::delete('posts/{post}/reactions/{type}', [PostReactionController::class, 'destroy'])->name('posts.reactions.destroy');

        Route::post('posts/{post}/comments', [CommentController::class, 'store'])
            ->middleware(['verified', 'throttle:comment'])->name('posts.comments.store');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('posts/{post}/request-consent', [ConsentController::class, 'request'])->name('posts.request-consent');
        Route::get('consent-requests', [ConsentController::class, 'index'])->name('consent-requests.index');
        Route::post('consent-requests/{post}/respond', [ConsentController::class, 'respond'])->name('consent-requests.respond');
    });

    // --- Programaciones y envíos recurrentes — contrato: docs/api/programaciones.md ---
    Route::middleware('feature:schedules')->group(function (): void {
        Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('schedules', [ScheduleController::class, 'store'])->middleware('verified')->name('schedules.store');
        Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->name('schedules.show');
        Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
        Route::get('schedules/{schedule}/occurrences', [ScheduleController::class, 'occurrences'])->name('schedules.occurrences');
        Route::put('schedules/{schedule}/occurrences/{date}/letter', [ScheduleController::class, 'assignLetter'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('schedules.occurrences.letter');
        Route::post('schedules/{schedule}/pause', [ScheduleController::class, 'pause'])->name('schedules.pause');
        Route::post('schedules/{schedule}/resume', [ScheduleController::class, 'resume'])->name('schedules.resume');
    });

    // --- Auto Memory Dolls — contrato: docs/api/dolls.md · ADR-0005 ---
    Route::middleware('feature:dolls')->group(function (): void {
        Route::get('dolls', [DollDirectoryController::class, 'index'])->name('dolls.index');
        Route::get('dolls/{handle}', [DollDirectoryController::class, 'show'])->name('dolls.show');

        Route::get('me/doll-profile', [MyDollProfileController::class, 'show'])->name('me.doll-profile.show');
        Route::post('me/doll-profile', [MyDollProfileController::class, 'store'])
            ->middleware('verified')->name('me.doll-profile.store');
        Route::patch('me/doll-profile', [MyDollProfileController::class, 'update'])->name('me.doll-profile.update');
        Route::post('me/doll-profile/availability', [MyDollProfileController::class, 'availability'])
            ->name('me.doll-profile.availability');

        Route::get('doll-requests', [DollRequestController::class, 'index'])->name('doll-requests.index');
        Route::post('doll-requests', [DollRequestController::class, 'store'])
            ->middleware(['verified', 'throttle:create-doll-request'])->name('doll-requests.store');
        Route::get('doll-requests/{dollRequest}', [DollRequestController::class, 'show'])->name('doll-requests.show');
        Route::post('doll-requests/{dollRequest}/accept', [DollRequestController::class, 'accept'])->name('doll-requests.accept');
        Route::post('doll-requests/{dollRequest}/reject', [DollRequestController::class, 'reject'])->name('doll-requests.reject');
        Route::post('doll-requests/{dollRequest}/start', [DollRequestController::class, 'start'])->name('doll-requests.start');
        Route::post('doll-requests/{dollRequest}/cancel', [DollRequestController::class, 'cancel'])->name('doll-requests.cancel');
        Route::post('doll-requests/{dollRequest}/rate', [DollRequestController::class, 'rate'])->name('doll-requests.rate');

        // Chat y borradores (3C). El canal se valida DOS veces: aqui en el
        // controlador y en routes/channels.php al suscribirse — ADR-0005.
        Route::get('doll-requests/{dollRequest}/messages', [DollChatController::class, 'index'])
            ->name('doll-requests.messages.index');
        Route::post('doll-requests/{dollRequest}/messages', [DollChatController::class, 'store'])
            ->middleware(['verified', 'throttle:doll-chat'])->name('doll-requests.messages.store');

        Route::post('doll-requests/{dollRequest}/drafts', [DollDraftController::class, 'store'])
            ->middleware(['verified', 'throttle:doll-chat'])->name('doll-requests.drafts.store');
        Route::post('doll-requests/{dollRequest}/drafts/{draft}/approve', [DollDraftController::class, 'approve'])
            ->scopeBindings()
            ->name('doll-requests.drafts.approve');
    });

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
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions/{id}', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});
