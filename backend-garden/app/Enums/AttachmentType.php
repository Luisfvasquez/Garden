<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachmentType: string
{
    case Image = 'image';
    case Audio = 'audio';
    case PressedFlower = 'pressed_flower';
}
