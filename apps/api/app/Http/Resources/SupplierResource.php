<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Supplier */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'contact' => $this->contact,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'supply_mode' => $this->supply_mode->label(),
            'payment_terms_days' => $this->payment_terms_days,
            'lead_time_days' => $this->lead_time_days,
            'status' => $this->status->label(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
