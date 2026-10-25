<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
