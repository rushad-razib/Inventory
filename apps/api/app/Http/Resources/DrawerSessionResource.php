<?php

namespace App\Http\Resources;

use App\Models\DrawerSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DrawerSession */
class DrawerSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cashier_id' => $this->cashier_id,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status->label(),
            'opening_amount' => $this->opening_amount,
            'counted_amount' => $this->counted_amount,
            'expected_amount' => $this->expected_amount,
            'variance' => $this->variance,
            'balance' => $this->status->label() === 'open' ? $this->balance() : 0,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->closed_by,
        ];
    }
}
