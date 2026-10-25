<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'unit_of_measure' => ['sometimes', 'string', 'max:255'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'reorder_point' => ['sometimes', 'integer', 'min:0'],
            'reorder_quantity' => ['sometimes', 'integer', 'min:0'],
            'preferred_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
