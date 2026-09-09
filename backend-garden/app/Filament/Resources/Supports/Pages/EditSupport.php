<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports\Pages;

use App\Filament\Resources\Supports\SupportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupport extends EditRecord
{
    protected static string $resource = SupportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
