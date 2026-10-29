<?php

namespace App\Enums;

enum TenderType: int
{
    case Cash = 1;
    case Card = 2;
    case Points = 3;

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'cash',
            self::Card => 'card',
            self::Points => 'points',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'cash' => self::Cash,
            'card' => self::Card,
            'points' => self::Points,
            default => throw new \InvalidArgumentException("Unknown tender type [{$label}]."),
        };
    }
}
