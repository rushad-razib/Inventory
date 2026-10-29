<?php

namespace App\Services;

use App\Enums\SupplierBillStatus;
use App\Models\Sale;
use App\Models\SaleLineAllocation;
use App\Models\SupplierBill;

final class ConsignmentPayables
{
    public function credit(SaleLineAllocation $allocation, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $bill = SupplierBill::query()
            ->where('source_type', $allocation->getMorphClass())
            ->where('source_id', $allocation->id)
            ->lockForUpdate()
            ->first();

        if ($bill === null || $bill->status !== SupplierBillStatus::Open) {
            return;
        }

        $open = $bill->amount - $bill->amount_paid - $bill->credited_amount;
        $bill->credited_amount += min($amount, max($open, 0));

        if ($bill->amount - $bill->amount_paid - $bill->credited_amount <= 0) {
            $bill->status = SupplierBillStatus::Credited;
        }

        $bill->save();
    }

    public function creditOpenBills(Sale $sale): void
    {
        $sale->loadMissing('lines.allocations');

        foreach ($sale->lines as $line) {
            foreach ($line->allocations as $allocation) {
                $bill = SupplierBill::query()
                    ->where('source_type', $allocation->getMorphClass())
                    ->where('source_id', $allocation->id)
                    ->lockForUpdate()
                    ->first();

                if ($bill === null || $bill->status !== SupplierBillStatus::Open) {
                    continue;
                }

                $open = $bill->amount - $bill->amount_paid - $bill->credited_amount;

                if ($open <= 0) {
                    continue;
                }

                $bill->credited_amount += $open;
                $bill->status = SupplierBillStatus::Credited;
                $bill->save();
            }
        }
    }
}
