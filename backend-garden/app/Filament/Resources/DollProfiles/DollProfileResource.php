<?php

declare(strict_types=1);

namespace App\Filament\Resources\DollProfiles;

use App\Filament\Resources\DollProfiles\Pages\EditDollProfile;
use App\Filament\Resources\DollProfiles\Pages\ListDollProfiles;
use App\Filament\Resources\DollProfiles\Schemas\DollProfileForm;
use App\Filament\Resources\DollProfiles\Tables\DollProfilesTable;
use App\Models\DollProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DollProfileResource extends Resource
{
    protected static ?string $model = DollProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Comunidad';

    protected static ?string $modelLabel = 'perfil Doll';

    protected static ?string $pluralModelLabel = 'Dolls';

    public static function form(Schema $schema): Schema
    {
        return DollProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DollProfilesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::whereNull('verified_at')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDollProfiles::route('/'),
            'edit' => EditDollProfile::route('/{record}/edit'),
        ];
    }
}
