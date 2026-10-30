<?php

namespace App\Services;

use App\Actions\ListReorderAlerts;
use App\Enums\AdjustmentStatus;
use App\Enums\CustomerReturnStatus;
use App\Enums\LotOwnership;
use App\Enums\PurchaseOrderStatus;
use App\Models\Adjustment;
use App\Models\CustomerReturn;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\SupplierBill;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DashboardQuery
{
    public function __construct(
        private readonly SoldUnits $soldUnits,
        private readonly DemandReport $demand,
        private readonly RoiReport $roi,
        private readonly CashBalances $cash,
        private readonly ListReorderAlerts $reorderAlerts,
    ) {}

    /**
     * @return array{tiles: array<string, int>, demand: list<array<string, mixed>>, roi: list<array<string, mixed>>}
     */
    public function payload(ReportWindow $window): array
    {
        return [
            'tiles' => $this->tiles(),
            'demand' => $this->demand->rows($window),
            'roi' => $this->roi->rows($window),
        ];
    }

    /**
     * @return array{
     *     owned_value: int,
     *     consignment_value: int,
     *     low_stock: int,
     *     sales_today: int,
     *     open_purchase_orders: int,
     *     pending_approvals: int,
     *     payables_due: int,
     *     till: int,
     *     bank: int
     * }
     */
    public function tiles(): array
    {
        $owned = 0;
        $consignment = 0;

        foreach (StockLot::query()->get(['ownership', 'qty_on_hand', 'unit_cost']) as $lot) {
            $value = $lot->qty_on_hand * $lot->unit_cost;

            if ($lot->ownership === LotOwnership::Owned) {
                $owned += $value;
            } else {
                $consignment += $value;
            }
        }

        $today = CarbonImmutable::now((string) config('app.timezone'))->toDateString();
        $start = CarbonImmutable::parse($today, (string) config('app.timezone'))->startOfDay();
        $end = CarbonImmutable::parse($today, (string) config('app.timezone'))->endOfDay();
        $salesToday = 0;

        foreach ($this->soldUnits->between($start, $end) as $row) {
            $salesToday += $row['net_taka'];
        }

        $cash = $this->cash->totals();

        return [
            'owned_value' => $owned,
            'consignment_value' => $consignment,
            'low_stock' => count(($this->reorderAlerts)()),
            'sales_today' => $salesToday,
            'open_purchase_orders' => PurchaseOrder::query()->whereIn('status', [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::PendingApproval,
                PurchaseOrderStatus::Approved,
                PurchaseOrderStatus::PartiallyReceived,
            ])->count(),
            'pending_approvals' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::PendingApproval)->count()
                + Adjustment::query()->where('status', AdjustmentStatus::PendingApproval)->count()
                + CustomerReturn::query()->where('status', CustomerReturnStatus::PendingApproval)->count(),
            'payables_due' => (int) SupplierBill::query()
                ->whereDate('due_on', '<=', $today)
                ->whereRaw('amount - amount_paid - credited_amount > 0')
                ->sum(DB::raw('amount - amount_paid - credited_amount')),
            'till' => $cash['till'],
            'bank' => $cash['bank'],
        ];
    }
}
