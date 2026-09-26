<?php

namespace App\Enums;

enum SystemRoleStatus: int
{
    case System = 1;
    case Custom = 0;

    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Custom => 'Custom',
        };
    }
}
