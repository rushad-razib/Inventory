<?php

namespace App\Http\Requests\SupplierSettlements;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use App\Models\SupplierSettlement;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierSettlementRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', SupplierSettlement::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bill_ids' => ['required', 'array', 'min:1'],
            'bill_ids.*' => ['required', 'uuid', 'distinct', 'exists:supplier_bills,id'],
        ];
    }
}
