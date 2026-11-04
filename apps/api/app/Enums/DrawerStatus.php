<?php

namespace App\Enums;

enum DrawerStatus: int
{
    case Open = 1;
    case Closed = 2;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'open',
            self::Closed => 'closed',
        };
    }
}
