<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReorderAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'warehouse_id' => $this->resource['warehouse_id'],
            'product_id' => $this->resource['product_id'],
            'on_hand' => $this->resource['on_hand'],
            'reorder_point' => $this->resource['reorder_point'],
            'reorder_quantity' => $this->resource['reorder_quantity'],
            'preferred_supplier_id' => $this->resource['preferred_supplier_id'],
            'warehouse' => $this->resource['warehouse'],
            'product' => $this->resource['product'],
            'preferred_supplier' => $this->resource['preferred_supplier'],
        ];
    }
}
