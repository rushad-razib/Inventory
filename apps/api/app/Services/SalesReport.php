<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Enums\TenderType;
use App\Models\Sale;

final class SalesReport
{
    /**
     * Posted tenders by day. A later refund does not rewrite the day. Voided sales are absent.
     *
     * @return list<array{date: string, sale_count: int, points_redeemed: int, cash: int, card: int}>
     */
    public function rows(ReportWindow $window): array
    {
        $sales = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereBetween('created_at', [$window->startsAt(), $window->endsAt()])
            ->with('tenders')
            ->get();

        $grouped = [];

        foreach ($sales as $sale) {
            $day = $sale->created_at?->timezone((string) config('app.timezone'))->toDateString() ?? $window->from;

            if (! isset($grouped[$day])) {
                $grouped[$day] = [
                    'date' => $day,
                    'sale_count' => 0,
                    'points_redeemed' => 0,
                    'cash' => 0,
                    'card' => 0,
                ];
            }

            $grouped[$day]['sale_count']++;
            $grouped[$day]['points_redeemed'] += $sale->points_redeemed;

            foreach ($sale->tenders as $tender) {
                if ($tender->type === TenderType::Cash) {
                    $grouped[$day]['cash'] += $tender->amount;
                }

                if ($tender->type === TenderType::Card) {
                    $grouped[$day]['card'] += $tender->amount;
                }
            }
        }

        $rows = array_values($grouped);
        usort($rows, fn (array $left, array $right): int => $left['date'] <=> $right['date']);

        return $rows;
    }
}
