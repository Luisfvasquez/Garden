<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country_code')->label('País (ISO-2, vacío = global)')->maxLength(2),
                TextInput::make('topic')->helperText('self_harm | grief | general'),
                TextInput::make('name')->required(),
                Textarea::make('description')->columnSpanFull(),
                TextInput::make('phone'),
                TextInput::make('sms'),
                TextInput::make('url')->url(),
                TextInput::make('hours'),
                TagsInput::make('languages'),
                TextInput::make('priority')->numeric()->default(0)->required(),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
