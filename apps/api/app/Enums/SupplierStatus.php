<?php

namespace App\Enums;

enum SupplierStatus: int
{
    case Active = 1;
    case Inactive = 2;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'active',
            self::Inactive => 'inactive',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'active' => self::Active,
            'inactive' => self::Inactive,
            default => throw new \InvalidArgumentException("Unknown supplier status [{$label}]."),
        };
    }
}
