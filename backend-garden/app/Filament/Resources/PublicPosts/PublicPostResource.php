<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicPosts;

use App\Filament\Resources\PublicPosts\Pages\CreatePublicPost;
use App\Filament\Resources\PublicPosts\Pages\EditPublicPost;
use App\Filament\Resources\PublicPosts\Pages\ListPublicPosts;
use App\Filament\Resources\PublicPosts\Schemas\PublicPostForm;
use App\Filament\Resources\PublicPosts\Tables\PublicPostsTable;
use App\Models\PublicPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PublicPostResource extends Resource
{
    protected static ?string $model = PublicPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Moderación';

    protected static ?string $modelLabel = 'publicación';

    protected static ?string $pluralModelLabel = 'publicaciones';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PublicPostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicPostsTable::configure($table);
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
            'index' => ListPublicPosts::route('/'),
            'create' => CreatePublicPost::route('/create'),
            'edit' => EditPublicPost::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
