<?php

namespace App\Http\Resources;

use App\Models\StockLot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockLot */
class StockLotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'supplier_id' => $this->supplier_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'unit_cost' => $this->unit_cost,
            'ownership' => $this->ownership->label(),
            'qty_received' => $this->qty_received,
            'qty_on_hand' => $this->qty_on_hand,
            'qty_damaged' => $this->qty_damaged,
            'received_at' => $this->received_at?->toIso8601String(),
            'warehouse' => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ],
            'product' => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ],
            'supplier' => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ],
        ];
    }
}
