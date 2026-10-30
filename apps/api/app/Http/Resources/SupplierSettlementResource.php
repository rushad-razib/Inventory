<?php

namespace App\Http\Resources;

use App\Models\SupplierBill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierSettlement */
class SupplierSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'amount' => $this->amount,
            'cash_entry_id' => $this->cash_entry_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ],
            'bills' => $this->bills->map(fn (SupplierBill $bill): array => [
                'id' => $bill->id,
                'amount' => (int) $bill->pivot->amount,
                'status' => $bill->status->label(),
            ])->values()->all(),
        ];
    }
}
