<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportCategory: string
{
    case Harassment = 'harassment';
    case Sexual = 'sexual';
    case Hate = 'hate';
    case Violence = 'violence';
    case SelfHarm = 'self_harm';
    case Spam = 'spam';
    case MinorSafety = 'minor_safety';
    case Other = 'other';

    /**
     * Auto-assigned severity. Minor-safety and self-harm are always critical —
     * they skip the queue (docs/api/comunidad-notificaciones.md).
     */
    public function severity(): ReportSeverity
    {
        return match ($this) {
            self::MinorSafety, self::SelfHarm => ReportSeverity::Critical,
            self::Hate, self::Violence, self::Sexual => ReportSeverity::High,
            self::Harassment => ReportSeverity::Medium,
            self::Spam, self::Other => ReportSeverity::Low,
        };
    }
}
