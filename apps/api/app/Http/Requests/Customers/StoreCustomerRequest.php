<?php

namespace App\Http\Requests\Customers;

use App\Support\BdMobile;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mobile = $this->input('mobile');

        if (is_string($mobile) && $mobile !== '') {
            try {
                $this->merge(['mobile' => BdMobile::normalize($mobile)]);
            } catch (InvalidArgumentException) {
                // Leave raw value; validation rules reject it.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^\+8801[3-9]\d{8}$/', 'unique:customers,mobile'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
