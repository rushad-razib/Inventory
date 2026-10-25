<?php

namespace App\Support;

use InvalidArgumentException;

final class BdMobile
{
    /**
     * Normalize a Bangladesh mobile to E.164 +8801XXXXXXXXX.
     *
     * Accepts +8801XXXXXXXXX or local 01XXXXXXXXX. Rejects other country codes.
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $mobile): string
    {
        $trimmed = preg_replace('/\s+/', '', trim($mobile)) ?? '';

        if (preg_match('/^\+8801[3-9]\d{8}$/', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^01[3-9]\d{8}$/', $trimmed) === 1) {
            return '+880'.substr($trimmed, 1);
        }

        throw new InvalidArgumentException('Mobile must be a Bangladesh number (+8801XXXXXXXXX or 01XXXXXXXXX).');
    }
}
