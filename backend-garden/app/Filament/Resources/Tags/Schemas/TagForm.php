<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
