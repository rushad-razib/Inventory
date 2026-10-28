<?php

namespace App\Http\Requests\Transfers;

use App\Http\Requests\Concerns\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

class TransferActionRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
