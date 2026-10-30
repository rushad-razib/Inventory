<?php

namespace App\Services;

final class RoiReport
{
    public function __construct(private readonly SoldUnits $soldUnits) {}

    /**
     * @return list<array{group_id: string|null, group_label: string, net_units: int, net_sales: int, cost: int, roi: string}>
     */
    public function rows(ReportWindow $window): array
    {
        $grouped = [];

        foreach ($this->soldUnits->between($window->startsAt(), $window->endsAt()) as $row) {
            $groupId = $window->group === 'category' ? $row['category_id'] : $row['product_id'];
            $groupLabel = $window->group === 'category' ? $row['category_label'] : $row['product_label'];
            $key = $groupId ?? 'none';

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'group_id' => $groupId,
                    'group_label' => $groupLabel,
                    'net_units' => 0,
                    'net_sales' => 0,
                    'cost' => 0,
                ];
            }

            $grouped[$key]['net_units'] += $row['net_units'];
            $grouped[$key]['net_sales'] += $row['net_taka'];
            $grouped[$key]['cost'] += $row['cost'];
        }

        $rows = [];

        foreach ($grouped as $row) {
            if ($row['cost'] <= 0) {
                continue;
            }

            $row['roi'] = SoldUnits::ratio($row['net_sales'], $row['cost']);
            $rows[] = $row;
        }

        usort($rows, fn (array $left, array $right): int => $left['group_label'] <=> $right['group_label']);

        return $rows;
    }
}
