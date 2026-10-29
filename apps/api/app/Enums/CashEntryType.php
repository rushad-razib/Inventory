<?php

namespace App\Enums;

enum CashEntryType: int
{
    case SaleCashIn = 1;
    case SaleCardIn = 2;
    case CustomerRefundOut = 3;
    case SupplierPaymentOut = 4;
    case SupplierRefundIn = 5;

    public function label(): string
    {
        return match ($this) {
            self::SaleCashIn => 'sale_cash_in',
            self::SaleCardIn => 'sale_card_in',
            self::CustomerRefundOut => 'customer_refund_out',
            self::SupplierPaymentOut => 'supplier_payment_out',
            self::SupplierRefundIn => 'supplier_refund_in',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'sale_cash_in' => self::SaleCashIn,
            'sale_card_in' => self::SaleCardIn,
            'customer_refund_out' => self::CustomerRefundOut,
            'supplier_payment_out' => self::SupplierPaymentOut,
            'supplier_refund_in' => self::SupplierRefundIn,
            default => throw new \InvalidArgumentException("Unknown cash entry type [{$label}]."),
        };
    }
}
