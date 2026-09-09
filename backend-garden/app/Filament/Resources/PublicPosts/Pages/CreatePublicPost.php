<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicPosts\Pages;

use App\Filament\Resources\PublicPosts\PublicPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicPost extends CreateRecord
{
    protected static string $resource = PublicPostResource::class;
}
