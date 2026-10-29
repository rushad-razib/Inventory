<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Sale::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'tenders' => ['present', 'array'],
            'tenders.*.type' => ['required', Rule::in(['cash', 'card', 'points'])],
            'tenders.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->appendIdempotencyKeyRule($validator);
    }
}
