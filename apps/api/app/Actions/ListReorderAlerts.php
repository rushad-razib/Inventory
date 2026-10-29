<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;

final class ListReorderAlerts
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(?string $warehouseId = null): array
    {
        $products = Product::query()
            ->with('preferredSupplier')
            ->where('track_stock', true)
            ->where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->orderBy('sku')
            ->get();

        $warehouses = Warehouse::query()
            ->when($warehouseId !== null && $warehouseId !== '', function ($query) use ($warehouseId) {
                $query->whereKey($warehouseId);
            })
            ->orderBy('code')
            ->get();

        $balances = StockBalance::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->get()
            ->keyBy(fn (StockBalance $balance): string => $balance->warehouse_id.'|'.$balance->product_id);

        $alerts = [];

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                $balance = $balances->get($warehouse->id.'|'.$product->id);
                $onHand = $balance instanceof StockBalance ? $balance->on_hand : 0;

                if ($onHand > $product->reorder_point) {
                    continue;
                }

                $supplier = $product->preferredSupplier;

                $alerts[] = [
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'on_hand' => $onHand,
                    'reorder_point' => $product->reorder_point,
                    'reorder_quantity' => $product->reorder_quantity,
                    'preferred_supplier_id' => $product->preferred_supplier_id,
                    'warehouse' => [
                        'id' => $warehouse->id,
                        'code' => $warehouse->code,
                        'name' => $warehouse->name,
                    ],
                    'product' => [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                    ],
                    'preferred_supplier' => $supplier === null ? null : [
                        'id' => $supplier->id,
                        'code' => $supplier->code,
                        'name' => $supplier->name,
                        'supply_mode' => $supplier->supply_mode->label(),
                    ],
                ];
            }
        }

        return $alerts;
    }
}
