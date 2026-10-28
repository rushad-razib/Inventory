<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LotOwnership;
use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockBalanceResource;
use App\Http\Resources\StockLotResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StockController extends Controller
{
    public function balances(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            return $this->forbidden('You do not have permission to view stock.');
        }

        $query = StockBalance::query()->with(['warehouse', 'product']);

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.product_id')) {
            $query->where('product_id', $request->input('filter.product_id'));
        }

        return ApiResponse::paginated(
            $query->orderBy('warehouse_id')->paginate($this->perPage($request)),
            StockBalanceResource::class,
        );
    }

    public function lots(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            return $this->forbidden('You do not have permission to view stock.');
        }

        $query = StockLot::query()->with(['warehouse', 'product', 'supplier']);

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.product_id')) {
            $query->where('product_id', $request->input('filter.product_id'));
        }

        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        if ($request->filled('filter.ownership')) {
            try {
                $query->where('ownership', LotOwnership::fromLabel($request->string('filter.ownership')->toString()));
            } catch (InvalidArgumentException) {
                return $this->badFilter('ownership');
            }
        }

        return ApiResponse::paginated(
            $query->orderBy('received_at')->paginate($this->perPage($request)),
            StockLotResource::class,
        );
    }

    public function movements(Request $request): JsonResponse
    {
        if (! $request->user()->can('inventory.view')) {
            return $this->forbidden('You do not have permission to view stock.');
        }

        $query = StockMovement::query()->with(['warehouse', 'product']);

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.product_id')) {
            $query->where('product_id', $request->input('filter.product_id'));
        }

        if ($request->filled('filter.type')) {
            try {
                $query->where('type', MovementType::fromLabel($request->string('filter.type')->toString()));
            } catch (InvalidArgumentException) {
                return $this->badFilter('type');
            }
        }

        if ($request->filled('filter.from')) {
            $query->where('occurred_at', '>=', $request->date('filter.from'));
        }

        if ($request->filled('filter.to')) {
            $query->where('occurred_at', '<=', $request->date('filter.to'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('occurred_at')->paginate($this->perPage($request)),
            StockMovementResource::class,
        );
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
