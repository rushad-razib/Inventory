<?php

namespace App\Enums;

enum TransferStatus: int
{
    case Draft = 1;
    case InTransit = 2;
    case Received = 3;
    case Cancelled = 4;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::InTransit => 'in_transit',
            self::Received => 'received',
            self::Cancelled => 'cancelled',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'draft' => self::Draft,
            'in_transit' => self::InTransit,
            'received' => self::Received,
            'cancelled' => self::Cancelled,
            default => throw new \InvalidArgumentException("Unknown transfer status [{$label}]."),
        };
    }
}
