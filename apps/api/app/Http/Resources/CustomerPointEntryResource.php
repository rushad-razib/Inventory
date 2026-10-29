<?php

namespace App\Http\Resources;

use App\Models\CustomerPointEntry;
use App\Models\CustomerReturn;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerPointEntry */
class CustomerPointEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type->label(),
            'points' => $this->points,
            'source_type' => match ($this->source_type) {
                Sale::class => 'sale',
                CustomerReturn::class => 'customer_return',
                default => $this->source_type,
            },
            'source_id' => $this->source_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
