<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';
    case Deleted = 'deleted';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
