<?php

namespace App\Enums;

enum MovementType: int
{
    case Receipt = 1;
    case TransferOut = 2;
    case TransferIn = 3;
    case Adjustment = 4;
    case Sale = 5;
    case CustomerReturnResaleable = 6;
    case CustomerReturnDamaged = 7;
    case SupplierReturn = 8;

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'receipt',
            self::TransferOut => 'transfer_out',
            self::TransferIn => 'transfer_in',
            self::Adjustment => 'adjustment',
            self::Sale => 'sale',
            self::CustomerReturnResaleable => 'customer_return_resaleable',
            self::CustomerReturnDamaged => 'customer_return_damaged',
            self::SupplierReturn => 'supplier_return',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'receipt' => self::Receipt,
            'transfer_out' => self::TransferOut,
            'transfer_in' => self::TransferIn,
            'adjustment' => self::Adjustment,
            'sale' => self::Sale,
            'customer_return_resaleable' => self::CustomerReturnResaleable,
            'customer_return_damaged' => self::CustomerReturnDamaged,
            'supplier_return' => self::SupplierReturn,
            default => throw new \InvalidArgumentException("Unknown movement type [{$label}]."),
        };
    }
}
