<?php

namespace App\Enums;

enum SupplierReturnStatus: int
{
    case Draft = 1;
    case Shipped = 2;
    case Accepted = 3;
    case RejectedBySupplier = 4;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::Shipped => 'shipped',
            self::Accepted => 'accepted',
            self::RejectedBySupplier => 'rejected_by_supplier',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'draft' => self::Draft,
            'shipped' => self::Shipped,
            'accepted' => self::Accepted,
            'rejected_by_supplier' => self::RejectedBySupplier,
            default => throw new \InvalidArgumentException("Unknown supplier return status [{$label}]."),
        };
    }
}
