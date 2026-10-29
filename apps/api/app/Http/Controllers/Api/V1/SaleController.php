<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PostSale;
use App\Actions\VoidSale;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $query = Sale::query()->with(Sale::apiRelations());

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.status')) {
            try {
                $query->where('status', SaleStatus::fromLabel((string) $request->input('filter.status')));
            } catch (InvalidArgumentException) {
                return ProblemResponse::make(
                    'validation-failed',
                    'Validation Failed',
                    422,
                    'One or more fields failed validation.',
                    ['status' => ['Unknown status.']],
                );
            }
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($this->perPage($request)),
            SaleResource::class,
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        $sale->load(Sale::apiRelations());

        return ApiResponse::data(new SaleResource($sale));
    }

    public function store(StoreSaleRequest $request, PostSale $action): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $result = $action(
            $request->user(),
            [
                'warehouse_id' => $request->string('warehouse_id')->toString(),
                'customer_id' => $request->filled('customer_id')
                    ? $request->string('customer_id')->toString()
                    : null,
                'lines' => collect($request->validated('lines'))
                    ->map(fn (array $line): array => [
                        'product_id' => $line['product_id'],
                        'quantity' => (int) $line['quantity'],
                    ])
                    ->all(),
                'tenders' => collect($request->validated('tenders'))
                    ->map(fn (array $tender): array => [
                        'type' => $tender['type'],
                        'amount' => (int) $tender['amount'],
                    ])
                    ->all(),
            ],
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }

    public function void(Request $request, Sale $sale, VoidSale $action): JsonResponse
    {
        $this->authorize('void', $sale);

        return ApiResponse::data($action($request->user(), $sale, $request));
    }
}
