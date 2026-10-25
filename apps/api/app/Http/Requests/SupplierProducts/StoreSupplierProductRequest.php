<?php

namespace App\Http\Requests\SupplierProducts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierProductRequest extends FormRequest
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
        $supplier = $this->route('supplier');

        return [
            'product_id' => [
                'required',
                'uuid',
                'exists:products,id',
                Rule::unique('supplier_products', 'product_id')->where(
                    fn ($query) => $query->where('supplier_id', $supplier?->getKey()),
                ),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'agreed_unit_cost' => ['required', 'integer', 'min:0'],
        ];
    }
}
