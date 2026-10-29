<?php

namespace App\Enums;

enum CustomerReturnStatus: int
{
    case PendingApproval = 1;
    case Approved = 2;
    case Rejected = 3;
    case Refunded = 4;

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'pending_approval',
            self::Approved => 'approved',
            self::Rejected => 'rejected',
            self::Refunded => 'refunded',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'pending_approval' => self::PendingApproval,
            'approved' => self::Approved,
            'rejected' => self::Rejected,
            'refunded' => self::Refunded,
            default => throw new \InvalidArgumentException("Unknown customer return status [{$label}]."),
        };
    }
}
