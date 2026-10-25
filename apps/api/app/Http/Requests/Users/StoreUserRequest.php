<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
