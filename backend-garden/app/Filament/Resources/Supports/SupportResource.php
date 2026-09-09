<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports;

use App\Filament\Resources\Supports\Pages\CreateSupport;
use App\Filament\Resources\Supports\Pages\EditSupport;
use App\Filament\Resources\Supports\Pages\ListSupports;
use App\Filament\Resources\Supports\Schemas\SupportForm;
use App\Filament\Resources\Supports\Tables\SupportsTable;
use App\Models\SupportResource as SupportResourceModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportResource extends Resource
{
    protected static ?string $model = SupportResourceModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogos';

    protected static ?string $modelLabel = 'recurso de ayuda';

    protected static ?string $pluralModelLabel = 'recursos de ayuda';

    public static function form(Schema $schema): Schema
    {
        return SupportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupports::route('/'),
            'create' => CreateSupport::route('/create'),
            'edit' => EditSupport::route('/{record}/edit'),
        ];
    }
}
