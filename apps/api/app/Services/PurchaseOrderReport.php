<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;

final class PurchaseOrderReport
{
    /**
     * @return list<array{
     *     id: string,
     *     number: string,
     *     supplier_name: string,
     *     status: string,
     *     quantity_ordered: int,
     *     quantity_received: int
     * }>
     */
    public function rows(): array
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'lines'])
            ->where('status', '!=', PurchaseOrderStatus::Cancelled)
            ->orderBy('number')
            ->get();

        return $orders->map(fn (PurchaseOrder $order): array => [
            'id' => $order->id,
            'number' => $order->number,
            'supplier_name' => $order->supplier?->name ?? '',
            'status' => $order->status->label(),
            'quantity_ordered' => (int) $order->lines->sum('quantity_ordered'),
            'quantity_received' => (int) $order->lines->sum('quantity_received'),
        ])->all();
    }
}
