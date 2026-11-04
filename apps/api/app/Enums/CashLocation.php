<?php

namespace App\Enums;

enum CashLocation: int
{
    case Till = 1;
    case Bank = 2;
    case Cash = 3;
    case Drawer = 4;

    public function label(): string
    {
        return match ($this) {
            self::Till, self::Cash => 'cash',
            self::Bank => 'bank',
            self::Drawer => 'drawer',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'till', 'cash' => self::Cash,
            'bank' => self::Bank,
            'drawer' => self::Drawer,
            default => throw new \InvalidArgumentException("Unknown cash location [{$label}]."),
        };
    }
}
