<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports\Pages;

use App\Filament\Resources\Supports\SupportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupport extends CreateRecord
{
    protected static string $resource = SupportResource::class;
}
