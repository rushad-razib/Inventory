<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
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
            'code' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('suppliers', 'code')->ignore($supplier),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'supply_mode' => ['sometimes', 'string', Rule::in(['outright', 'consignment'])],
            'payment_terms_days' => ['sometimes', 'integer', 'min:0'],
            'lead_time_days' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
