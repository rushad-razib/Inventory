<?php

namespace App\Enums;

enum CashLocation: int
{
    case Till = 1;
    case Bank = 2;

    public function label(): string
    {
        return match ($this) {
            self::Till => 'till',
            self::Bank => 'bank',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'till' => self::Till,
            'bank' => self::Bank,
            default => throw new \InvalidArgumentException("Unknown cash location [{$label}]."),
        };
    }
}
