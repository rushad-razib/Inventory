<?php

namespace App\Http\Resources;

use App\Models\CreditNote;
use App\Models\GoodsReceipt;
use App\Models\SaleLineAllocation;
use App\Models\SupplierBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierBill */
class SupplierBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'source_type' => match ($this->source_type) {
                GoodsReceipt::class => 'goods_receipt',
                SaleLineAllocation::class => 'sale_allocation',
                default => $this->source_type,
            },
            'source_id' => $this->source_id,
            'status' => $this->status->label(),
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'credited_amount' => $this->credited_amount,
            'due_on' => $this->due_on?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ],
            'credit_notes' => $this->creditNotes->map(fn (CreditNote $note): array => [
                'id' => $note->id,
                'supplier_return_id' => $note->supplier_return_id,
                'amount' => $note->amount,
            ])->values()->all(),
        ];
    }
}
