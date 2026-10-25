<?php

namespace App\Enums;

enum SupplyMode: int
{
    case Outright = 1;
    case Consignment = 2;

    public function label(): string
    {
        return match ($this) {
            self::Outright => 'outright',
            self::Consignment => 'consignment',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'outright' => self::Outright,
            'consignment' => self::Consignment,
            default => throw new \InvalidArgumentException("Unknown supply mode [{$label}]."),
        };
    }
}
