<?php

namespace App\Enums;

enum LotOwnership: int
{
    case Owned = 1;
    case Consignment = 2;

    public function label(): string
    {
        return match ($this) {
            self::Owned => 'owned',
            self::Consignment => 'consignment',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'owned' => self::Owned,
            'consignment' => self::Consignment,
            default => throw new \InvalidArgumentException("Unknown lot ownership [{$label}]."),
        };
    }
}
