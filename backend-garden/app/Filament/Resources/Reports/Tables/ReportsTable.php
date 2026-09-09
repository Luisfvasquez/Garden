<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Tables;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The moderation queue, priority-ordered by severity then age
 * (backend-garden/docs/moderacion.md).
 */
class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('severity')->badge()->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('category')->badge(),
                TextColumn::make('reportable_type')->label('Objetivo')
                    ->formatStateUsing(fn ($state): string => is_object($state) ? $state->value : (string) $state),
                TextColumn::make('reportable_id')->label('ID objetivo')->limit(12)->copyable(),
                TextColumn::make('reporter.postal_handle')->label('Reporta'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('details')->limit(60)->wrap()->toggleable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReportStatus::class)
                    ->default(ReportStatus::Open->value),
                SelectFilter::make('severity')->options([
                    'critical' => 'Crítico', 'high' => 'Alto', 'medium' => 'Medio', 'low' => 'Bajo',
                ]),
                SelectFilter::make('category')->options(ReportCategory::class),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirmar')
                    ->color('danger')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status !== ReportStatus::Actioned)
                    ->action(fn (Report $record) => $record->markActioned(auth()->user())),

                Action::make('dismiss')
                    ->label('Descartar')
                    ->color('gray')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status !== ReportStatus::Dismissed)
                    ->action(fn (Report $record) => $record->markDismissed(auth()->user())),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw(
                "array_position(ARRAY['critical','high','medium','low']::text[], severity)"
            )->orderByDesc('created_at'));
    }
}
