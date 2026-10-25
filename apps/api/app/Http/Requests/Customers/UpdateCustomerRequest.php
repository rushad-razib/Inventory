<?php

namespace App\Http\Requests\Customers;

use App\Support\BdMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateCustomerRequest extends FormRequest
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
        $customer = $this->route('customer');

        return [
            'mobile' => [
                'sometimes',
                'string',
                'regex:/^\+8801[3-9]\d{8}$/',
                Rule::unique('customers', 'mobile')->ignore($customer),
            ],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
