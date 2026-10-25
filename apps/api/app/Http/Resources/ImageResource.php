<?php

namespace App\Http\Resources;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Image */
class ImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => Storage::disk($this->disk)->url($this->path),
            'mime_type' => $this->mime_type,
            'byte_size' => $this->byte_size,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
