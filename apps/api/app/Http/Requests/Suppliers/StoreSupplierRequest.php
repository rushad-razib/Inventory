<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'supply_mode' => ['required', 'string', Rule::in(['outright', 'consignment'])],
            'payment_terms_days' => ['sometimes', 'integer', 'min:0'],
            'lead_time_days' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
