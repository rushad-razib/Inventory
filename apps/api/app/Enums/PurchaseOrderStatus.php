<?php

namespace App\Enums;

enum PurchaseOrderStatus: int
{
    case Draft = 1;
    case PendingApproval = 2;
    case Approved = 3;
    case Rejected = 4;
    case PartiallyReceived = 5;
    case Received = 6;
    case Cancelled = 7;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::PendingApproval => 'pending_approval',
            self::Approved => 'approved',
            self::Rejected => 'rejected',
            self::PartiallyReceived => 'partially_received',
            self::Received => 'received',
            self::Cancelled => 'cancelled',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'draft' => self::Draft,
            'pending_approval' => self::PendingApproval,
            'approved' => self::Approved,
            'rejected' => self::Rejected,
            'partially_received' => self::PartiallyReceived,
            'received' => self::Received,
            'cancelled' => self::Cancelled,
            default => throw new \InvalidArgumentException("Unknown purchase order status [{$label}]."),
        };
    }
}
