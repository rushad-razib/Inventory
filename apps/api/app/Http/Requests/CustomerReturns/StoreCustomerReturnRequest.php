<?php

namespace App\Http\Requests\CustomerReturns;

use App\Models\CustomerReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', CustomerReturn::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_line_id' => ['required', 'uuid', 'distinct', 'exists:sale_lines,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.reason' => ['required', Rule::in(['resaleable', 'damaged'])],
        ];
    }
}
