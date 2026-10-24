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
     *     department: string|null,
     *     warehouse_id: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'department' => null,
            'warehouse_id' => null,
        ];
    }
}
