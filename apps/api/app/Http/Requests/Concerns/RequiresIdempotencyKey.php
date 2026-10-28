<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait RequiresIdempotencyKey
{
    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }

    public function idempotencyHash(): string
    {
        $payload = $this->json()->all();

        if (! is_array($payload)) {
            $payload = [];
        }

        $this->sortKeys($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function withValidator(Validator $validator): void
    {
        $this->appendIdempotencyKeyRule($validator);
    }

    public function appendIdempotencyKeyRule(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $key = $this->header('Idempotency-Key');

            if (! is_string($key) || $key === '' || strlen($key) > 255) {
                $validator->errors()->add('Idempotency-Key', 'The Idempotency-Key header is required.');
            }
        });
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function sortKeys(array &$payload): void
    {
        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sortKeys($value);
            }
        }

        unset($value);

        if (! array_is_list($payload)) {
            ksort($payload);
        }
    }
}
