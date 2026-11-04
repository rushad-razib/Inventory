<?php

namespace App\Http\Requests\Drawers;

use Illuminate\Foundation\Http\FormRequest;

class OpenDrawerRequest extends FormRequest
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
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'opening_balance' => ['required', 'integer', 'min:1'],
        ];
    }
}
