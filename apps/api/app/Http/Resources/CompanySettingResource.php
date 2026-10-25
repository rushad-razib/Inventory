<?php

namespace App\Http\Resources;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanySetting */
class CompanySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'po_approval_threshold' => $this->po_approval_threshold,
            'adjustment_approval_threshold' => $this->adjustment_approval_threshold,
            'points_earn_per' => $this->points_earn_per,
            'point_redeem_value' => $this->point_redeem_value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
