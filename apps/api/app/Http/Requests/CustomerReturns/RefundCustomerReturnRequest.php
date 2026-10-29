<?php

namespace App\Http\Requests\CustomerReturns;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RefundCustomerReturnRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        $return = $this->route('customer_return');

        return $return !== null && (bool) $this->user()?->can('refund', $return);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $this->appendIdempotencyKeyRule($validator);
    }
}
