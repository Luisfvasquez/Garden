<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports\Pages;

use App\Filament\Resources\Supports\SupportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupports extends ListRecords
{
    protected static string $resource = SupportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
