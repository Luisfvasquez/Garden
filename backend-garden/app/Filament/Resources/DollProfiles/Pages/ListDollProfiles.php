<?php

declare(strict_types=1);

namespace App\Filament\Resources\DollProfiles\Pages;

use App\Filament\Resources\DollProfiles\DollProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDollProfiles extends ListRecords
{
    protected static string $resource = DollProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
