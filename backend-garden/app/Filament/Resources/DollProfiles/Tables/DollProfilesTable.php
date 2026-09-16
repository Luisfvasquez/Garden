<?php

declare(strict_types=1);

namespace App\Filament\Resources\DollProfiles\Tables;

use App\Models\DollProfile;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Verification queue: `verified_at IS NULL` is the pending review pile
 * (docs/api/dolls.md — "verificación manual antes de activar el rol").
 */
class DollProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.postal_handle')->label('Usuario')->searchable(),
                TextColumn::make('headline')->limit(40),
                TextColumn::make('rate_type')->badge(),
                IconColumn::make('is_available')->label('Disponible')->boolean(),
                IconColumn::make('verified_at')->label('Verificado')->boolean(),
                TextColumn::make('rating_avg')->label('Valoración')->numeric(2),
                TextColumn::make('completed_requests_count')->label('Completadas'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('verified_at')
                    ->label('Verificado')
                    ->placeholder('Todos')
                    ->trueLabel('Verificado')
                    ->falseLabel('Pendiente de revisión')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('verified_at'),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    )
                    ->default(false),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('verify')
                    ->label('Verificar')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->visible(fn (DollProfile $record): bool => ! $record->isVerified())
                    ->action(fn (DollProfile $record) => $record->markVerified()),

                Action::make('unverify')
                    ->label('Retirar verificación')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (DollProfile $record): bool => $record->isVerified())
                    ->action(fn (DollProfile $record) => $record->markUnverified()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
