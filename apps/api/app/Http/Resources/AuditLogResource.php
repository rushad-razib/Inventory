<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'auditable_type' => class_basename($this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'actor_id' => $this->actor_id,
            'before' => $this->before,
            'after' => $this->after,
            'created_at' => $this->created_at?->toIso8601String(),
            'actor' => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ],
        ];
    }
}
