<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineAllocation;
use App\Support\LargestRemainder;
use Carbon\CarbonImmutable;

final class SoldUnits
{
    /**
     * Units still sold on completed sales in the window.
     * A pending return is still sold. A recorded refund has already reduced quantity_returned.
     *
     * @return list<array{
     *     day: string,
     *     product_id: string,
     *     product_label: string,
     *     category_id: string|null,
     *     category_label: string,
     *     net_units: int,
     *     net_taka: int,
     *     cost: int
     * }>
     */
    public function between(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sales = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->with(['lines.product.category', 'lines.allocations'])
            ->get();

        $rows = [];

        foreach ($sales as $sale) {
            $day = $sale->created_at?->timezone((string) config('app.timezone'))->toDateString() ?? $start->toDateString();

            foreach ($sale->lines as $line) {
                $row = $this->line($line, $day);

                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{
     *     day: string,
     *     product_id: string,
     *     product_label: string,
     *     category_id: string|null,
     *     category_label: string,
     *     net_units: int,
     *     net_taka: int,
     *     cost: int
     * }|null
     */
    private function line(SaleLine $line, string $day): ?array
    {
        $sold = $line->quantity - $line->quantity_returned;

        if ($sold <= 0 || $line->product === null) {
            return null;
        }

        $returnedPoints = LargestRemainder::cumulativeSlice(
            $line->points_redeemed,
            $line->quantity,
            0,
            $line->quantity_returned,
        );

        return [
            'day' => $day,
            'product_id' => $line->product_id,
            'product_label' => $line->product->sku.' · '.$line->product->name,
            'category_id' => $line->product->category_id,
            'category_label' => $line->product->category?->name ?? 'Uncategorized',
            'net_units' => $sold,
            'net_taka' => ($line->unit_price * $sold) - ($line->points_redeemed - $returnedPoints),
            'cost' => $this->remainingCost($line, $sold),
        ];
    }

    private function remainingCost(SaleLine $line, int $sold): int
    {
        $skip = $line->quantity_returned;
        $need = $sold;
        $cost = 0;

        $allocations = $line->allocations->sortBy('id')->values();

        foreach ($allocations as $allocation) {
            if (! $allocation instanceof SaleLineAllocation || $need === 0) {
                break;
            }

            $available = $allocation->quantity;

            if ($skip >= $available) {
                $skip -= $available;

                continue;
            }

            $available -= $skip;
            $skip = 0;
            $take = min($need, $available);
            $cost += $allocation->unit_cost * $take;
            $need -= $take;
        }

        return $cost;
    }

    public static function ratio(int $netSales, int $cost): string
    {
        $numerator = ($netSales - $cost) * 10000;
        $negative = $numerator < 0;
        $numerator = abs($numerator);
        $scaled = intdiv($numerator, $cost);
        $remainder = $numerator % $cost;

        if ($remainder * 2 >= $cost) {
            $scaled++;
        }

        $whole = intdiv($scaled, 10000);
        $fraction = $scaled % 10000;

        return sprintf('%s%d.%04d', $negative ? '-' : '', $whole, $fraction);
    }
}
