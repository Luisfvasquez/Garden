<?php

declare(strict_types=1);

namespace App\Filament\Resources\DollProfiles\Pages;

use App\Filament\Resources\DollProfiles\DollProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDollProfile extends EditRecord
{
    protected static string $resource = DollProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
