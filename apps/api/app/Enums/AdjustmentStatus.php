<?php

namespace App\Enums;

enum AdjustmentStatus: int
{
    case PendingApproval = 1;
    case Posted = 2;
    case Rejected = 3;

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'pending_approval',
            self::Posted => 'posted',
            self::Rejected => 'rejected',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'pending_approval' => self::PendingApproval,
            'posted' => self::Posted,
            'rejected' => self::Rejected,
            default => throw new \InvalidArgumentException("Unknown adjustment status [{$label}]."),
        };
    }
}
