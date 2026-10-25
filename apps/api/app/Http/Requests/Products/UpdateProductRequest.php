<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $product = $this->route('product');

        return [
            'sku' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($product),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')->ignore($product),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'unit_of_measure' => ['sometimes', 'string', 'max:255'],
            'sell_price' => ['sometimes', 'integer', 'min:0'],
            'reorder_point' => ['sometimes', 'integer', 'min:0'],
            'reorder_quantity' => ['sometimes', 'integer', 'min:0'],
            'preferred_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
