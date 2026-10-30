<?php

namespace App\Services;

final class DemandReport
{
    public function __construct(private readonly SoldUnits $soldUnits) {}

    /**
     * @return list<array{date: string, group_id: string|null, group_label: string, net_units: int, net_taka: int}>
     */
    public function rows(ReportWindow $window): array
    {
        $grouped = [];

        foreach ($this->soldUnits->between($window->startsAt(), $window->endsAt()) as $row) {
            $groupId = $window->group === 'category' ? $row['category_id'] : $row['product_id'];
            $groupLabel = $window->group === 'category' ? $row['category_label'] : $row['product_label'];
            $key = $row['day'].'|'.($groupId ?? 'none');

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'date' => $row['day'],
                    'group_id' => $groupId,
                    'group_label' => $groupLabel,
                    'net_units' => 0,
                    'net_taka' => 0,
                ];
            }

            $grouped[$key]['net_units'] += $row['net_units'];
            $grouped[$key]['net_taka'] += $row['net_taka'];
        }

        $rows = array_values($grouped);

        usort($rows, function (array $left, array $right): int {
            return [$left['date'], $left['group_label']] <=> [$right['date'], $right['group_label']];
        });

        return $rows;
    }
}
