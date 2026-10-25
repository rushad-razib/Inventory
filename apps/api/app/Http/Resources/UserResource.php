<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     roles: list<string>,
     *     permissions: list<string>,
     *     department: array{id: string, name: string}|null,
     *     warehouse_id: string|null,
     *     is_active: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('department');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'department' => $this->department
                ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ]
                : null,
            'warehouse_id' => $this->warehouse_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
