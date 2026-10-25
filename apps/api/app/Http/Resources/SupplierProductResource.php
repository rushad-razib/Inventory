<?php

namespace App\Http\Resources;

use App\Models\SupplierProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierProduct */
class SupplierProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'product_id' => $this->product_id,
            'supplier_sku' => $this->supplier_sku,
            'agreed_unit_cost' => $this->agreed_unit_cost,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
