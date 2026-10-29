<?php

namespace App\Enums;

enum SupplierBillStatus: int
{
    case Open = 1;
    case Settled = 2;
    case Credited = 3;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'open',
            self::Settled => 'settled',
            self::Credited => 'credited',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'open' => self::Open,
            'settled' => self::Settled,
            'credited' => self::Credited,
            default => throw new \InvalidArgumentException("Unknown supplier bill status [{$label}]."),
        };
    }
}
