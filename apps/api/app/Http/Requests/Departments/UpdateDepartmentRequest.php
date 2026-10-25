<?php

namespace App\Http\Requests\Departments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
        $department = $this->route('department');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department),
            ],
        ];
    }
}
