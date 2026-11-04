<?php

namespace App\Enums;

enum CashEntryType: int
{
    case SaleCashIn = 1;
    case SaleCardIn = 2;
    case CustomerRefundOut = 3;
    case SupplierPaymentOut = 4;
    case SupplierRefundIn = 5;
    case DrawerFloat = 6;
    case DrawerDeposit = 7;
    case DrawerShortage = 8;
    case DrawerOverage = 9;
    case CashAdjustment = 10;

    public function label(): string
    {
        return match ($this) {
            self::SaleCashIn => 'sale_cash_in',
            self::SaleCardIn => 'sale_card_in',
            self::CustomerRefundOut => 'customer_refund_out',
            self::SupplierPaymentOut => 'supplier_payment_out',
            self::SupplierRefundIn => 'supplier_refund_in',
            self::DrawerFloat => 'drawer_float',
            self::DrawerDeposit => 'drawer_deposit',
            self::DrawerShortage => 'drawer_shortage',
            self::DrawerOverage => 'drawer_overage',
            self::CashAdjustment => 'cash_adjustment',
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
            'drawer_float' => self::DrawerFloat,
            'drawer_deposit' => self::DrawerDeposit,
            'drawer_shortage' => self::DrawerShortage,
            'drawer_overage' => self::DrawerOverage,
            'cash_adjustment' => self::CashAdjustment,
            default => throw new \InvalidArgumentException("Unknown cash entry type [{$label}]."),
        };
    }
}
