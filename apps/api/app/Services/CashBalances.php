<?php

namespace App\Services;

use App\Enums\CashDirection;
use App\Enums\CashLocation;
use App\Models\CashEntry;
use Illuminate\Database\Eloquent\Builder;

final class CashBalances
{
    /**
     * @return array{cash: int, bank: int, drawer: int}
     */
    public function totals(): array
    {
        return [
            'cash' => $this->balance(CashLocation::Cash) + $this->balance(CashLocation::Till),
            'bank' => $this->balance(CashLocation::Bank),
            'drawer' => $this->balance(CashLocation::Drawer),
        ];
    }

    public function balance(CashLocation $location): int
    {
        return $this->sum(CashEntry::query()->where('location', $location));
    }

    /**
     * @param  Builder<CashEntry>  $query
     */
    private function sum($query): int
    {
        $total = 0;

        foreach ($query->selectRaw('direction, SUM(amount) as total')->groupBy('direction')->get() as $row) {
            $direction = $row->direction instanceof CashDirection
                ? $row->direction
                : CashDirection::from((int) $row->direction);
            $total += ($direction === CashDirection::In ? 1 : -1) * (int) $row->total;
        }

        return $total;
    }
}
