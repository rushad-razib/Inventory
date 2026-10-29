<?php

namespace App\Enums;

enum ReturnReason: int
{
    case Resaleable = 1;
    case Damaged = 2;

    public function label(): string
    {
        return match ($this) {
            self::Resaleable => 'resaleable',
            self::Damaged => 'damaged',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'resaleable' => self::Resaleable,
            'damaged' => self::Damaged,
            default => throw new \InvalidArgumentException("Unknown return reason [{$label}]."),
        };
    }
}
