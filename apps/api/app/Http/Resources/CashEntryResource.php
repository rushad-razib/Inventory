<?php

namespace App\Http\Resources;

use App\Models\CashEntry;
use App\Models\CustomerReturn;
use App\Models\Sale;
use App\Models\SupplierRefundReceivable;
use App\Models\SupplierSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CashEntry */
class CashEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location' => $this->location->label(),
            'direction' => $this->direction->label(),
            'type' => $this->type->label(),
            'amount' => $this->amount,
            'source_type' => match ($this->source_type) {
                Sale::class => 'sale',
                CustomerReturn::class => 'customer_return',
                SupplierSettlement::class => 'supplier_settlement',
                SupplierRefundReceivable::class => 'supplier_refund_receivable',
                default => $this->source_type,
            },
            'source_id' => $this->source_id,
            'actor_id' => $this->actor_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'actor' => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
        ];
    }
}
