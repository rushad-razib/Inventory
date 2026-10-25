<?php

namespace App\Http\Requests\Warehouses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('warehouses', 'code')->ignore($warehouse),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
