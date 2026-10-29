<?php

namespace App\Http\Resources;

use App\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoodsReceipt */
class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'actor_id' => $this->actor_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
                'supply_mode' => $this->supplier->supply_mode->label(),
            ],
            'warehouse' => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ],
            'actor' => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
            'purchase_order' => $this->purchaseOrder === null ? null : [
                'id' => $this->purchaseOrder->id,
                'number' => $this->purchaseOrder->number,
                'status' => $this->purchaseOrder->status->label(),
            ],
            'lines' => $this->lines->map(fn ($line): array => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'quantity' => $line->quantity,
                'unit_cost' => $line->unit_cost,
                'product' => [
                    'id' => $line->product->id,
                    'sku' => $line->product->sku,
                    'name' => $line->product->name,
                ],
            ])->values()->all(),
            'lots' => $this->lots->map(fn ($lot): array => [
                'id' => $lot->id,
                'product_id' => $lot->product_id,
                'ownership' => $lot->ownership->label(),
                'unit_cost' => $lot->unit_cost,
                'qty_received' => $lot->qty_received,
                'qty_on_hand' => $lot->qty_on_hand,
            ])->values()->all(),
            'bill' => $this->bill === null ? null : [
                'id' => $this->bill->id,
                'status' => $this->bill->status->label(),
                'amount' => $this->bill->amount,
                'amount_paid' => $this->bill->amount_paid,
                'due_on' => $this->bill->due_on?->toDateString(),
            ],
        ];
    }
}
