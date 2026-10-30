<?php

namespace App\Http\Resources;

use App\Models\SupplierReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierReturn */
class SupplierReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_lot_id' => $this->stock_lot_id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'credited_amount' => $this->credited_amount,
            'customer_return_id' => $this->customer_return_id,
            'sale_line_allocation_id' => $this->sale_line_allocation_id,
            'status' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601String(),
            'lot' => [
                'id' => $this->lot->id,
                'ownership' => $this->lot->ownership->label(),
                'qty_damaged' => $this->lot->qty_damaged,
                'warehouse' => [
                    'id' => $this->lot->warehouse->id,
                    'code' => $this->lot->warehouse->code,
                    'name' => $this->lot->warehouse->name,
                ],
                'product' => [
                    'id' => $this->lot->product->id,
                    'sku' => $this->lot->product->sku,
                    'name' => $this->lot->product->name,
                ],
                'supplier' => $this->lot->supplier === null ? null : [
                    'id' => $this->lot->supplier->id,
                    'code' => $this->lot->supplier->code,
                    'name' => $this->lot->supplier->name,
                ],
            ],
            'credit_note' => $this->creditNote === null ? null : [
                'id' => $this->creditNote->id,
                'supplier_bill_id' => $this->creditNote->supplier_bill_id,
                'amount' => $this->creditNote->amount,
            ],
            'refund_receivable' => $this->refundReceivable === null ? null : [
                'id' => $this->refundReceivable->id,
                'amount' => $this->refundReceivable->amount,
                'status' => $this->refundReceivable->status->label(),
            ],
        ];
    }
}
