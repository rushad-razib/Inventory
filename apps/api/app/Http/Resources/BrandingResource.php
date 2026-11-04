<?php

namespace App\Http\Resources;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanySetting */
class BrandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'logo_url' => $this->publicAssetUrl($this->logo_path),
            'favicon_url' => $this->publicAssetUrl($this->favicon_path),
        ];
    }
}
