<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country_code')->label('País')->placeholder('Global')->sortable(),
                TextColumn::make('topic')->badge()->placeholder('—'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('url')->url(fn ($record) => $record->url)->openUrlInNewTab()->limit(40),
                TextColumn::make('priority')->numeric()->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('priority', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
