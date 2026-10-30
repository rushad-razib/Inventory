<?php

namespace App\Services;

use App\Enums\LotOwnership;
use App\Models\StockLot;

final class StockReport
{
    /**
     * @return list<array{
     *     warehouse_id: string,
     *     warehouse_code: string,
     *     warehouse_name: string,
     *     product_id: string,
     *     sku: string,
     *     product_name: string,
     *     owned_on_hand: int,
     *     consignment_on_hand: int,
     *     owned_value: int,
     *     consignment_value: int
     * }>
     */
    public function rows(): array
    {
        $grouped = [];

        $lots = StockLot::query()->with(['warehouse', 'product'])->orderBy('id')->get();

        foreach ($lots as $lot) {
            if ($lot->warehouse === null || $lot->product === null || $lot->qty_on_hand === 0) {
                continue;
            }

            $key = $lot->warehouse_id.'|'.$lot->product_id;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'warehouse_id' => $lot->warehouse_id,
                    'warehouse_code' => $lot->warehouse->code,
                    'warehouse_name' => $lot->warehouse->name,
                    'product_id' => $lot->product_id,
                    'sku' => $lot->product->sku,
                    'product_name' => $lot->product->name,
                    'owned_on_hand' => 0,
                    'consignment_on_hand' => 0,
                    'owned_value' => 0,
                    'consignment_value' => 0,
                ];
            }

            if ($lot->ownership === LotOwnership::Owned) {
                $grouped[$key]['owned_on_hand'] += $lot->qty_on_hand;
                $grouped[$key]['owned_value'] += $lot->qty_on_hand * $lot->unit_cost;
            } else {
                $grouped[$key]['consignment_on_hand'] += $lot->qty_on_hand;
                $grouped[$key]['consignment_value'] += $lot->qty_on_hand * $lot->unit_cost;
            }
        }

        $rows = array_values($grouped);
        usort($rows, fn (array $left, array $right): int => [$left['warehouse_code'], $left['sku']] <=> [$right['warehouse_code'], $right['sku']]);

        return $rows;
    }
}
