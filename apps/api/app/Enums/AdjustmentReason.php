<?php

namespace App\Enums;

enum AdjustmentReason: int
{
    case Damage = 1;
    case Count = 2;
    case Correction = 3;

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'damage',
            self::Count => 'count',
            self::Correction => 'correction',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'damage' => self::Damage,
            'count' => self::Count,
            'correction' => self::Correction,
            default => throw new \InvalidArgumentException("Unknown adjustment reason [{$label}]."),
        };
    }
}
