<?php

namespace App\Enums;

enum SaleStatus: int
{
    case Completed = 1;
    case Voided = 2;

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'completed',
            self::Voided => 'voided',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'completed' => self::Completed,
            'voided' => self::Voided,
            default => throw new \InvalidArgumentException("Unknown sale status [{$label}]."),
        };
    }
}
