<?php

namespace App\Http\Requests\SupplierProducts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierProductRequest extends FormRequest
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
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'agreed_unit_cost' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
