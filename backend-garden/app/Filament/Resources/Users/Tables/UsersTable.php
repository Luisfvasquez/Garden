<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\ModerationActionSource;
use App\Enums\ModerationActionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ModerationAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()
                    ->description(fn (User $record): string => $record->postal_handle),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                IconColumn::make('email_verified_at')->label('Verificado')->boolean(),
                IconColumn::make('accepts_random_letters')->label('Aleatorias')->boolean()->toggleable(),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable(),
                TextColumn::make('last_active_at')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                SelectFilter::make('status')->options(UserStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('suspend')
                    ->label('Suspender')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Active)
                    ->action(fn (User $record) => $record->forceFill(['status' => UserStatus::Suspended])->save()),

                Action::make('reactivate')
                    ->label('Reactivar')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Suspended)
                    ->action(fn (User $record) => $record->forceFill(['status' => UserStatus::Active])->save()),

                Action::make('restrictRandom')
                    ->label('Restringir aleatorias')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => ! ModerationAction::restrictsRandom($record))
                    ->action(function (User $record): void {
                        ModerationAction::create([
                            'user_id' => $record->getKey(),
                            'type' => ModerationActionType::RestrictRandom,
                            'source' => ModerationActionSource::Manual,
                            'reason' => 'Restricción manual desde el panel.',
                            'created_by' => auth()->id(),
                            'expires_at' => now()->addDays(30),
                        ]);
                    }),

                Action::make('liftRestriction')
                    ->label('Levantar restricción')
                    ->color('success')
                    ->visible(fn (User $record): bool => ModerationAction::restrictsRandom($record))
                    ->action(function (User $record): void {
                        ModerationAction::query()
                            ->where('user_id', $record->getKey())
                            ->where('type', ModerationActionType::RestrictRandom)
                            ->inForce()
                            ->update(['lifted_at' => now()]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
