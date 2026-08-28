<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Doll = 'doll';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function isStaff(): bool
    {
        return match ($this) {
            self::Moderator, self::Admin => true,
            default => false,
        };
    }
}
