<?php

namespace App\Http\Requests\Drawers;

use Illuminate\Foundation\Http\FormRequest;

class CloseDrawerRequest extends FormRequest
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
            'counted_amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
