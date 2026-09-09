<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Staff only adjust role, status and profile fields — never the credentials.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('pen_name'),
                TextInput::make('postal_handle')->disabled(),
                TextInput::make('email')->email()->disabled(),
                Select::make('role')->options(UserRole::class)->required(),
                Select::make('status')->options(UserStatus::class)->required(),
                Textarea::make('bio')->columnSpanFull(),
            ]);
    }
}
