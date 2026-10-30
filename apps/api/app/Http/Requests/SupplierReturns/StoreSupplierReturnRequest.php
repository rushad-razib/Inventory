<?php

namespace App\Http\Requests\SupplierReturns;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierReturnRequest extends FormRequest
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
            'stock_lot_id' => ['required', 'uuid', 'exists:stock_lots,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
