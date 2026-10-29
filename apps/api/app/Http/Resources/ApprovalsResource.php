<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'purchase_orders' => PurchaseOrderResource::collection($this->resource['purchase_orders'])->resolve(),
            'adjustments' => AdjustmentResource::collection($this->resource['adjustments'])->resolve(),
            'customer_returns' => [],
        ];
    }
}
