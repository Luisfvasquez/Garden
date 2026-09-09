<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicPosts\Pages;

use App\Filament\Resources\PublicPosts\PublicPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicPosts extends ListRecords
{
    protected static string $resource = PublicPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
