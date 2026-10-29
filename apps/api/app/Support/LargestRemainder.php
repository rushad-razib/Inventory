<?php

namespace App\Support;

final class LargestRemainder
{
    /**
     * Split an integer total across weights. Ties go to the lower id.
     *
     * @param  list<array{id: string, weight: int}>  $parts
     * @return array<string, int>
     */
    public static function split(array $parts, int $total): array
    {
        $shares = [];

        foreach ($parts as $part) {
            $shares[$part['id']] = 0;
        }

        $weightSum = array_sum(array_column($parts, 'weight'));

        if ($total === 0 || $weightSum === 0) {
            return $shares;
        }

        $remainders = [];
        $assigned = 0;

        foreach ($parts as $part) {
            $numerator = $total * $part['weight'];
            $base = intdiv($numerator, $weightSum);
            $shares[$part['id']] = $base;
            $assigned += $base;
            $remainders[] = [
                'id' => $part['id'],
                'remainder' => $numerator % $weightSum,
            ];
        }

        usort($remainders, function (array $left, array $right): int {
            if ($left['remainder'] === $right['remainder']) {
                return $left['id'] <=> $right['id'];
            }

            return $right['remainder'] <=> $left['remainder'];
        });

        $left = $total - $assigned;

        for ($index = 0; $index < $left; $index++) {
            $shares[$remainders[$index]['id']]++;
        }

        return $shares;
    }

    public static function cumulativeSlice(int $stored, int $quantity, int $already, int $taking): int
    {
        if ($quantity <= 0 || $taking <= 0 || $stored === 0) {
            return 0;
        }

        $after = $already + $taking;

        return intdiv($stored * $after, $quantity) - intdiv($stored * $already, $quantity);
    }
}
