<?php

namespace App\Http\Requests\Adjustments;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdjustmentRequest extends FormRequest
{
    use RequiresIdempotencyKey;

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
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', Rule::in(['damage', 'count', 'correction'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->appendIdempotencyKeyRule($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->input('reason') === 'damage' && (int) $this->input('quantity') < 1) {
                $validator->errors()->add('quantity', 'Damage quantity must be positive.');
            }
        });
    }
}
