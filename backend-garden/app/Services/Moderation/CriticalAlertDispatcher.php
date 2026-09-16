<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Notifications\CriticalModerationAlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The real escalation for `minor_safety` / `self_harm` at critical severity —
 * always logged, and emailed to the team when `MODERATION_ALERT_EMAIL` is set
 * (backend-garden/docs/moderacion.md §Escalado crítico).
 *
 * Never blocks the request it's called from: a misconfigured mailer must not
 * turn a critical alert into a 500 for the end user, so failures are swallowed
 * and logged instead.
 */
class CriticalAlertDispatcher
{
    /**
     * @param  array<string, string>  $context
     */
    public function alert(string $subject, string $summary, array $context = []): void
    {
        Log::critical($subject, [...$context, 'summary' => $summary]);

        $email = trim((string) config('moderation.critical_alert_email'));
        if ($email === '') {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new CriticalModerationAlertNotification($subject, $summary, $context));
        } catch (Throwable $e) {
            Log::error('Failed to send the critical moderation alert email', [
                'error' => $e->getMessage(),
                'subject' => $subject,
            ]);
        }
    }

    /**
     * The shared policy for a `flagged` verdict on held content: `minor_safety`
     * / `self_harm` skips the normal queue and alerts the team; anything else
     * just gets logged for the human review queue. Called from every surface
     * that holds content instead of rejecting it outright — random letters,
     * anonymous replies, blog posts, blog comments — so the rule lives once
     * (docs/moderacion.md §Autolesión: qué NO hacer).
     *
     * @param  array<string, string>  $context
     */
    public function alertIfCritical(string $event, ModerationVerdict $verdict, string $summary, array $context = []): void
    {
        $context = [...$context, 'categories' => implode(', ', $verdict->toArray()['categories'])];

        if ($verdict->isCritical()) {
            $this->alert($event, $summary, $context);

            return;
        }

        Log::warning($event, $context);
    }
}
