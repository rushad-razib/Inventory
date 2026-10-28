<?php

namespace App\Http\Requests\Transfers;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Transfer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id', 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
