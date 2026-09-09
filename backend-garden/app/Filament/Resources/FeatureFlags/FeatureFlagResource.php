<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeatureFlags;

use App\Filament\Resources\FeatureFlags\Pages\CreateFeatureFlag;
use App\Filament\Resources\FeatureFlags\Pages\EditFeatureFlag;
use App\Filament\Resources\FeatureFlags\Pages\ListFeatureFlags;
use App\Filament\Resources\FeatureFlags\Schemas\FeatureFlagForm;
use App\Filament\Resources\FeatureFlags\Tables\FeatureFlagsTable;
use App\Models\FeatureFlag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    public static function form(Schema $schema): Schema
    {
        return FeatureFlagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeatureFlagsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureFlags::route('/'),
            'create' => CreateFeatureFlag::route('/create'),
            'edit' => EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
