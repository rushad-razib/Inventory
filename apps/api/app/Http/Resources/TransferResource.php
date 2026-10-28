<?php

namespace App\Http\Resources;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transfer */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_warehouse_id' => $this->source_warehouse_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'status' => $this->status->label(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'source_warehouse' => [
                'id' => $this->sourceWarehouse->id,
                'code' => $this->sourceWarehouse->code,
                'name' => $this->sourceWarehouse->name,
            ],
            'destination_warehouse' => [
                'id' => $this->destinationWarehouse->id,
                'code' => $this->destinationWarehouse->code,
                'name' => $this->destinationWarehouse->name,
            ],
            'lines' => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'quantity' => $line->quantity,
                'product' => [
                    'id' => $line->product->id,
                    'sku' => $line->product->sku,
                    'name' => $line->product->name,
                ],
                'lots' => $line->lots->map(fn ($slice) => [
                    'id' => $slice->id,
                    'source_lot_id' => $slice->source_lot_id,
                    'destination_lot_id' => $slice->destination_lot_id,
                    'supplier_id' => $slice->supplier_id,
                    'ownership' => $slice->ownership->label(),
                    'unit_cost' => $slice->unit_cost,
                    'quantity' => $slice->quantity,
                ])->all(),
            ])->all(),
        ];
    }
}
