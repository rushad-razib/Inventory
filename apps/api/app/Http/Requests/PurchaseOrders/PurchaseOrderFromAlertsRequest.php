<?php

namespace App\Http\Requests\PurchaseOrders;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderFromAlertsRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
        ];
    }
}
