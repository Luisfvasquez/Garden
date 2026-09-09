<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicPosts\Pages;

use App\Filament\Resources\PublicPosts\PublicPostResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPublicPost extends EditRecord
{
    protected static string $resource = PublicPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
