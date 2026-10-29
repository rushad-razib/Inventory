<?php

namespace App\Enums;

enum CashDirection: int
{
    case In = 1;
    case Out = 2;

    public function label(): string
    {
        return match ($this) {
            self::In => 'in',
            self::Out => 'out',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::In => self::Out,
            self::Out => self::In,
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'in' => self::In,
            'out' => self::Out,
            default => throw new \InvalidArgumentException("Unknown cash direction [{$label}]."),
        };
    }
}
