<?php

namespace App\Services;

use App\Enums\CashDirection;
use App\Enums\CashLocation;
use App\Models\CashEntry;

final class CashBalances
{
    /**
     * @return array{till: int, bank: int}
     */
    public function totals(): array
    {
        $totals = [
            CashLocation::Till->label() => 0,
            CashLocation::Bank->label() => 0,
        ];

        $rows = CashEntry::query()
            ->selectRaw('location, direction, SUM(amount) as total')
            ->groupBy('location', 'direction')
            ->get();

        foreach ($rows as $row) {
            $location = $row->location instanceof CashLocation
                ? $row->location
                : CashLocation::from((int) $row->location);
            $direction = $row->direction instanceof CashDirection
                ? $row->direction
                : CashDirection::from((int) $row->direction);
            $sign = $direction === CashDirection::In ? 1 : -1;
            $totals[$location->label()] += $sign * (int) $row->total;
        }

        return $totals;
    }
}
