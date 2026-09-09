<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicPosts\Tables;

use App\Enums\ModerationStatus;
use App\Models\PublicPost;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Doubles as the blog moderation queue: default filter is `flagged`, with
 * approve / reject actions (docs/api/blog.md — nothing is silently deleted).
 */
class PublicPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('type')->badge(),
                TextColumn::make('author.postal_handle')->label('Autor'),
                TextColumn::make('moderation_status')->badge()->sortable()
                    ->color(fn (string $state): string => $state === 'approved' ? 'success' : ($state === 'flagged' ? 'warning' : 'danger')),
                TextColumn::make('consent_status')->badge(),
                TextColumn::make('published_at')->dateTime('Y-m-d H:i')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('moderation_status')
                    ->options(ModerationStatus::class)
                    ->default(ModerationStatus::Flagged->value),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn (PublicPost $record): bool => $record->moderation_status !== ModerationStatus::Approved)
                    ->action(function (PublicPost $record): void {
                        $record->forceFill(['moderation_status' => ModerationStatus::Approved])->save();
                        $record->publishIfReady();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->visible(fn (PublicPost $record): bool => $record->moderation_status !== ModerationStatus::Rejected)
                    ->action(function (PublicPost $record): void {
                        $record->forceFill(['moderation_status' => ModerationStatus::Rejected, 'published_at' => null])->save();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
