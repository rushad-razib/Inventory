<?php

namespace App\Http\Requests\Images;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageRequest extends FormRequest
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
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
