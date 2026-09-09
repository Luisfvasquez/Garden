<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeatureFlags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FeatureFlagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description'),
                Toggle::make('enabled')
                    ->required(),
                TextInput::make('rollout'),
            ]);
    }
}
