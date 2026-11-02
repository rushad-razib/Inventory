<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('current_password') === '') {
            $this->merge(['current_password' => null]);
        }

        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()),
            ],
            'current_password' => ['nullable', 'string', 'current_password'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $emailChanged = $this->exists('email') && $this->string('email')->toString() !== $user->email;
            $passwordChanging = $this->filled('password');

            if (($emailChanged || $passwordChanging) && ! $this->filled('current_password')) {
                $validator->errors()->add(
                    'current_password',
                    'The current password is required.',
                );
            }
        });
    }
}
