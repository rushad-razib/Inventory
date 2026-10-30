<?php

namespace App\Services;

use App\Enums\RefundReceivableStatus;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierRefundReceivable;

final class SupplierSpendReport
{
    /**
     * @return list<array{supplier_id: string, supplier_name: string, billed: int, paid: int, refunded: int}>
     */
    public function rows(): array
    {
        $billed = SupplierBill::query()
            ->selectRaw('supplier_id, SUM(amount) as billed, SUM(amount_paid) as paid')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $refunded = SupplierRefundReceivable::query()
            ->where('status', RefundReceivableStatus::Received)
            ->selectRaw('supplier_id, SUM(amount) as refunded')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $ids = $billed->keys()->merge($refunded->keys())->unique()->all();
        $suppliers = Supplier::query()->whereIn('id', $ids)->get()->keyBy('id');
        $rows = [];

        foreach ($ids as $id) {
            $bill = $billed->get($id);
            $refund = $refunded->get($id);
            $row = [
                'supplier_id' => (string) $id,
                'supplier_name' => $suppliers->get($id)?->name ?? '',
                'billed' => (int) ($bill->billed ?? 0),
                'paid' => (int) ($bill->paid ?? 0),
                'refunded' => (int) ($refund->refunded ?? 0),
            ];

            if ($row['billed'] === 0 && $row['paid'] === 0 && $row['refunded'] === 0) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, fn (array $left, array $right): int => $left['supplier_name'] <=> $right['supplier_name']);

        return $rows;
    }
}
