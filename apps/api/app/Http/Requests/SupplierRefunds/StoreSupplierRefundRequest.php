<?php

namespace App\Http\Requests\SupplierRefunds;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use App\Models\SupplierReturn;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRefundRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('refund', SupplierReturn::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_return_id' => ['required', 'uuid', 'exists:supplier_returns,id'],
        ];
    }
}
