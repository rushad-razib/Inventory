<?php

namespace App\Http\Resources;

use App\Models\CustomerReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerReturn */
class CustomerReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'requester_id' => $this->requester_id,
            'approver_id' => $this->approver_id,
            'status' => $this->status->label(),
            'cash_amount' => (int) $this->lines->sum('cash_amount'),
            'points_restored' => (int) $this->lines->sum('points_restored'),
            'points_clawed_back' => (int) $this->lines->sum('points_clawed_back'),
            'created_at' => $this->created_at?->toIso8601String(),
            'sale' => [
                'id' => $this->sale->id,
                'number' => $this->sale->number,
                'warehouse_id' => $this->sale->warehouse_id,
                'status' => $this->sale->status->label(),
                'warehouse' => $this->sale->warehouse === null ? null : [
                    'id' => $this->sale->warehouse->id,
                    'code' => $this->sale->warehouse->code,
                    'name' => $this->sale->warehouse->name,
                ],
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
                'sale_line_id' => $line->sale_line_id,
                'quantity' => $line->quantity,
                'reason' => $line->reason->label(),
                'cash_amount' => $line->cash_amount,
                'points_restored' => $line->points_restored,
                'points_clawed_back' => $line->points_clawed_back,
                'product' => [
                    'id' => $line->saleLine->product->id,
                    'sku' => $line->saleLine->product->sku,
                    'name' => $line->saleLine->product->name,
                ],
            ])->values()->all(),
            'supplier_returns' => $this->supplierReturns->map(fn ($supplierReturn): array => [
                'id' => $supplierReturn->id,
                'stock_lot_id' => $supplierReturn->stock_lot_id,
                'quantity' => $supplierReturn->quantity,
                'unit_cost' => $supplierReturn->unit_cost,
                'status' => $supplierReturn->status->label(),
            ])->values()->all(),
        ];
    }
}
