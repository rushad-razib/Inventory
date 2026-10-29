<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'status' => $this->status->label(),
            'requester_id' => $this->requester_id,
            'approver_id' => $this->approver_id,
            'expected_on' => $this->expected_on?->toDateString(),
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
                'supply_mode' => $this->supplier->supply_mode->label(),
                'payment_terms_days' => $this->supplier->payment_terms_days,
            ],
            'destination_warehouse' => [
                'id' => $this->destinationWarehouse->id,
                'code' => $this->destinationWarehouse->code,
                'name' => $this->destinationWarehouse->name,
            ],
            'requester' => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ],
            'approver' => $this->approver === null ? null : [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ],
            'lines' => $this->lines->map(fn ($line): array => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'quantity_ordered' => $line->quantity_ordered,
                'quantity_received' => $line->quantity_received,
                'unit_cost' => $line->unit_cost,
                'product' => [
                    'id' => $line->product->id,
                    'sku' => $line->product->sku,
                    'name' => $line->product->name,
                ],
            ])->values()->all(),
        ];
    }
}
