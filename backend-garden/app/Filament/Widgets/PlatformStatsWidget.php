<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\DeliveryStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\LetterDelivery;
use App\Models\PublicPost;
use App\Models\Report;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // The postal clock: queued deliveries already past their leave time.
        $overdue = LetterDelivery::query()
            ->where('status', DeliveryStatus::Queued)
            ->where('scheduled_for', '<=', now()->subMinutes(15))
            ->count();

        $pendingModeration = $this->openReports()
            + PublicPost::where('moderation_status', ModerationStatus::Flagged)->count()
            + Comment::where('moderation_status', ModerationStatus::Flagged)->count()
            + LetterDelivery::where('status', DeliveryStatus::Held)->count();

        return [
            Stat::make('Usuarios', (string) User::count())
                ->description(User::where('created_at', '>=', now()->subWeek())->count().' esta semana'),

            Stat::make('En tránsito', (string) LetterDelivery::where('status', DeliveryStatus::InTransit)->count())
                ->description('Cartas viajando ahora'),

            Stat::make('Reloj postal', $overdue === 0 ? 'OK' : "{$overdue} atrasadas")
                ->description($overdue === 0 ? 'Sin retrasos' : 'Entregas vencidas sin despachar')
                ->color($overdue === 0 ? 'success' : 'danger'),

            Stat::make('Moderación pendiente', (string) $pendingModeration)
                ->description($this->openReports().' reportes abiertos')
                ->color($pendingModeration === 0 ? 'gray' : 'warning'),
        ];
    }

    private function openReports(): int
    {
        return Report::whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])->count();
    }
}
