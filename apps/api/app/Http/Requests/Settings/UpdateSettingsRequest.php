<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'po_approval_threshold' => ['sometimes', 'integer', 'min:0'],
            'adjustment_approval_threshold' => ['sometimes', 'integer', 'min:0'],
            'points_earn_per' => ['sometimes', 'integer', 'min:1'],
            'point_redeem_value' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
