<?php

namespace App\Http\Resources;

use App\Models\Adjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Adjustment */
class AdjustmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'reason' => $this->reason->label(),
            'status' => $this->status->label(),
            'requester_id' => $this->requester_id,
            'approver_id' => $this->approver_id,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
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
            'requester' => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ],
        ];
    }
}
