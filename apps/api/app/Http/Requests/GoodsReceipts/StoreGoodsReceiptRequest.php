<?php

namespace App\Http\Requests\GoodsReceipts;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use App\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', GoodsReceipt::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required_without:supplier_id', 'uuid', 'exists:purchase_orders,id', 'prohibits:supplier_id,warehouse_id'],
            'supplier_id' => ['required_without:purchase_order_id', 'uuid', 'exists:suppliers,id'],
            'warehouse_id' => ['required_with:supplier_id', 'uuid', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->appendIdempotencyKeyRule($validator);
    }
}
