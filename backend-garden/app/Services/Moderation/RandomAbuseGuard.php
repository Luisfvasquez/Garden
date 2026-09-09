<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\DeliveryMode;
use App\Enums\ModerationActionSource;
use App\Enums\ModerationActionType;
use App\Enums\ReportableType;
use App\Enums\ReportStatus;
use App\Models\LetterDelivery;
use App\Models\ModerationAction;
use App\Models\Report;
use App\Models\User;

/**
 * Automatic consequence from ADR-0004: a user who accrues N confirmed reports on
 * their random letters is auto-restricted from sending more.
 */
class RandomAbuseGuard
{
    /**
     * Re-evaluate a user after one of their random letters was reported and the
     * report upheld. Idempotent — a user already restricted is left alone.
     */
    public function afterReportActioned(Report $report): void
    {
        if ($report->reportable_type !== ReportableType::LetterDelivery) {
            return;
        }

        $delivery = LetterDelivery::find($report->reportable_id);
        if ($delivery === null
            || $delivery->delivery_mode !== DeliveryMode::Random
            || $delivery->sender_id === null) {
            return;
        }

        $offender = User::find($delivery->sender_id);
        if ($offender === null || ModerationAction::restrictsRandom($offender)) {
            return;
        }

        $confirmed = Report::query()
            ->where('reportable_type', ReportableType::LetterDelivery)
            ->where('status', ReportStatus::Actioned)
            ->whereIn('reportable_id', function ($q) use ($offender): void {
                $q->select('id')
                    ->from('letter_deliveries')
                    ->where('sender_id', $offender->getKey())
                    ->where('delivery_mode', DeliveryMode::Random->value);
            })
            ->count();

        $threshold = (int) config('moderation.random_abuse.reports_to_restrict', 2);
        if ($confirmed < $threshold) {
            return;
        }

        $days = (int) config('moderation.random_abuse.restrict_days', 30);

        ModerationAction::create([
            'user_id' => $offender->getKey(),
            'type' => ModerationActionType::RestrictRandom,
            'source' => ModerationActionSource::Report,
            'reason' => "Auto: {$confirmed} confirmed reports on random letters.",
            'context' => ['confirmed_reports' => $confirmed, 'trigger_report_id' => $report->getKey()],
            'expires_at' => $days > 0 ? now()->addDays($days) : null,
        ]);
    }
}
