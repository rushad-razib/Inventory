<?php

namespace App\Exceptions;

use RuntimeException;

class LedgerException extends RuntimeException
{
    public function __construct(
        public readonly string $type,
        string $detail,
        public readonly int $status = 409,
    ) {
        parent::__construct($detail);
    }

    public function title(): string
    {
        return match ($this->type) {
            'insufficient-stock' => 'Insufficient Stock',
            'idempotency-conflict' => 'Idempotency Conflict',
            'invalid-state' => 'Invalid State',
            'forbidden' => 'Forbidden',
            'untracked-product' => 'Untracked Product',
            default => 'Conflict',
        };
    }
}
