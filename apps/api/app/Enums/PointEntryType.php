<?php

namespace App\Enums;

enum PointEntryType: int
{
    case Earn = 1;
    case Redeem = 2;
    case EarnReversal = 3;
    case RedeemReversal = 4;

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'earn',
            self::Redeem => 'redeem',
            self::EarnReversal => 'earn_reversal',
            self::RedeemReversal => 'redeem_reversal',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'earn' => self::Earn,
            'redeem' => self::Redeem,
            'earn_reversal' => self::EarnReversal,
            'redeem_reversal' => self::RedeemReversal,
            default => throw new \InvalidArgumentException("Unknown point entry type [{$label}]."),
        };
    }
}
