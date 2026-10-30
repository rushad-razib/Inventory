<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesReportWindow;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\CsvResponse;
use App\Http\Responses\ProblemResponse;
use App\Services\DemandReport;
use App\Services\MovementReport;
use App\Services\PurchaseOrderReport;
use App\Services\ReportWindow;
use App\Services\RoiReport;
use App\Services\SalesReport;
use App\Services\StockReport;
use App\Services\SupplierSpendReport;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ResolvesReportWindow;

    public function __construct(
        private readonly DemandReport $demand,
        private readonly RoiReport $roi,
        private readonly SalesReport $sales,
        private readonly StockReport $stock,
        private readonly MovementReport $movements,
        private readonly SupplierSpendReport $supplierSpend,
        private readonly PurchaseOrderReport $purchaseOrders,
    ) {}

    #[QueryParameter('from', description: 'Start date YYYY-MM-DD', type: 'string')]
    #[QueryParameter('to', description: 'End date YYYY-MM-DD', type: 'string')]
    #[QueryParameter('group', description: 'product or category', type: 'string')]
    public function demand(Request $request): JsonResponse
    {
        $window = $this->guard($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return ApiResponse::data($this->demand->rows($window), 200, $window->meta());
    }

    #[QueryParameter('from', description: 'Start date YYYY-MM-DD', type: 'string')]
    #[QueryParameter('to', description: 'End date YYYY-MM-DD', type: 'string')]
    #[QueryParameter('group', description: 'product or category', type: 'string')]
    public function roi(Request $request): JsonResponse
    {
        $window = $this->guard($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return ApiResponse::data($this->roi->rows($window), 200, $window->meta());
    }

    #[QueryParameter('from', description: 'Start date YYYY-MM-DD', type: 'string')]
    #[QueryParameter('to', description: 'End date YYYY-MM-DD', type: 'string')]
    public function sales(Request $request): JsonResponse
    {
        $window = $this->guard($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return ApiResponse::data($this->sales->rows($window), 200, $window->meta());
    }

    public function stock(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        return ApiResponse::data($this->stock->rows());
    }

    #[QueryParameter('filter[type]', description: 'Movement type', type: 'string')]
    #[QueryParameter('filter[warehouse_id]', description: 'Warehouse id', type: 'string')]
    #[QueryParameter('filter[product_id]', description: 'Product id', type: 'string')]
    #[QueryParameter('filter[from]', description: 'Occurred at or after', type: 'string')]
    #[QueryParameter('filter[to]', description: 'Occurred at or before', type: 'string')]
    public function movements(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        if (! $this->movements->typeIsKnown($request)) {
            return $this->badFilter('type');
        }

        return ApiResponse::paginated(
            $this->movements->query($request)->paginate($this->perPage($request)),
            StockMovementResource::class,
        );
    }

    public function supplierSpend(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        return ApiResponse::data($this->supplierSpend->rows());
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        return ApiResponse::data($this->purchaseOrders->rows());
    }

    public function export(Request $request, string $report): JsonResponse|StreamedResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $table = $this->table($request, $report);

        if ($table instanceof JsonResponse) {
            return $table;
        }

        return CsvResponse::make($report.'.csv', $table['headers'], $table['rows']);
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}|JsonResponse
     */
    private function table(Request $request, string $report): array|JsonResponse
    {
        return match ($report) {
            'demand' => $this->demandCsv($request),
            'roi' => $this->roiCsv($request),
            'sales' => $this->salesCsv($request),
            'stock' => [
                'headers' => ['warehouse_code', 'warehouse_name', 'sku', 'product_name', 'owned_on_hand', 'consignment_on_hand', 'owned_value', 'consignment_value'],
                'rows' => array_map(fn (array $row): array => [
                    $row['warehouse_code'],
                    $row['warehouse_name'],
                    $row['sku'],
                    $row['product_name'],
                    $row['owned_on_hand'],
                    $row['consignment_on_hand'],
                    $row['owned_value'],
                    $row['consignment_value'],
                ], $this->stock->rows()),
            ],
            'movements' => $this->movementCsv($request),
            'supplier-spend' => [
                'headers' => ['supplier_name', 'billed', 'paid', 'refunded'],
                'rows' => array_map(fn (array $row): array => [
                    $row['supplier_name'],
                    $row['billed'],
                    $row['paid'],
                    $row['refunded'],
                ], $this->supplierSpend->rows()),
            ],
            'purchase-orders' => [
                'headers' => ['number', 'supplier_name', 'status', 'quantity_ordered', 'quantity_received'],
                'rows' => array_map(fn (array $row): array => [
                    $row['number'],
                    $row['supplier_name'],
                    $row['status'],
                    $row['quantity_ordered'],
                    $row['quantity_received'],
                ], $this->purchaseOrders->rows()),
            ],
            default => ProblemResponse::make('not-found', 'Not Found', 404, 'That report does not exist.'),
        };
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}|JsonResponse
     */
    private function demandCsv(Request $request): array|JsonResponse
    {
        $window = $this->reportWindow($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return [
            'headers' => ['date', 'group_label', 'net_units', 'net_taka'],
            'rows' => array_map(fn (array $row): array => [
                $row['date'],
                $row['group_label'],
                $row['net_units'],
                $row['net_taka'],
            ], $this->demand->rows($window)),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}|JsonResponse
     */
    private function roiCsv(Request $request): array|JsonResponse
    {
        $window = $this->reportWindow($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return [
            'headers' => ['group_label', 'net_units', 'net_sales', 'cost', 'roi'],
            'rows' => array_map(fn (array $row): array => [
                $row['group_label'],
                $row['net_units'],
                $row['net_sales'],
                $row['cost'],
                $row['roi'],
            ], $this->roi->rows($window)),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}|JsonResponse
     */
    private function salesCsv(Request $request): array|JsonResponse
    {
        $window = $this->reportWindow($request);

        if ($window instanceof JsonResponse) {
            return $window;
        }

        return [
            'headers' => ['date', 'sale_count', 'points_redeemed', 'cash', 'card'],
            'rows' => array_map(fn (array $row): array => [
                $row['date'],
                $row['sale_count'],
                $row['points_redeemed'],
                $row['cash'],
                $row['card'],
            ], $this->sales->rows($window)),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|null>>}|JsonResponse
     */
    private function movementCsv(Request $request): array|JsonResponse
    {
        if (! $this->movements->typeIsKnown($request)) {
            return $this->badFilter('type');
        }

        $rows = [];

        foreach ($this->movements->query($request)->get() as $movement) {
            $rows[] = [
                $movement->occurred_at?->toIso8601String(),
                $movement->warehouse?->code,
                $movement->product?->sku,
                $movement->type->label(),
                $movement->quantity,
                $movement->unit_cost,
            ];
        }

        return [
            'headers' => ['occurred_at', 'warehouse_code', 'sku', 'type', 'quantity', 'unit_cost'],
            'rows' => $rows,
        ];
    }

    private function guard(Request $request): ReportWindow|JsonResponse
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        return $this->reportWindow($request);
    }

    private function denied(Request $request): ?JsonResponse
    {
        if (! $request->user()->can('reports.view')) {
            return $this->forbidden('You do not have permission to view reports.');
        }

        return null;
    }

    private function badFilter(string $field): JsonResponse
    {
        return ProblemResponse::make(
            'validation-failed',
            'Validation Failed',
            422,
            'One or more fields failed validation.',
            [$field => ["Unknown {$field}."]],
        );
    }
}
