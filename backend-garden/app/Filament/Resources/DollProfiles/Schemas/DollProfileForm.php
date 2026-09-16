<?php

declare(strict_types=1);

namespace App\Filament\Resources\DollProfiles\Schemas;

use App\Enums\DollRateType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DollProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('headline')->required(),
                Textarea::make('bio')->columnSpanFull(),
                TagsInput::make('specialties'),
                TagsInput::make('languages'),
                TagsInput::make('tone_tags'),
                Select::make('rate_type')->options(DollRateType::class)->required(),
                TextInput::make('rate_amount')->numeric(),
                TextInput::make('currency')->maxLength(3),
                Toggle::make('is_available'),
                TextInput::make('max_concurrent_requests')->numeric()->required(),
            ]);
    }
}
