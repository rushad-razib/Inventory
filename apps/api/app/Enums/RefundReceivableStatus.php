<?php

namespace App\Enums;

enum RefundReceivableStatus: int
{
    case Open = 1;
    case Received = 2;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'open',
            self::Received => 'received',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'open' => self::Open,
            'received' => self::Received,
            default => throw new \InvalidArgumentException("Unknown refund receivable status [{$label}]."),
        };
    }
}
