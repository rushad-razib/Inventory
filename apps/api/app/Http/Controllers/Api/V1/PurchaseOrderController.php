<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelPurchaseOrder;
use App\Actions\CreatePurchaseOrdersFromAlerts;
use App\Actions\DecidePurchaseOrder;
use App\Actions\SavePurchaseOrder;
use App\Actions\SubmitPurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrders\PurchaseOrderFromAlertsRequest;
use App\Http\Requests\PurchaseOrders\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrders\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::query()->with(PurchaseOrder::apiRelations());

        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        if ($request->filled('filter.status')) {
            try {
                $query->where(
                    'status',
                    PurchaseOrderStatus::fromLabel((string) $request->input('filter.status')),
                );
            } catch (InvalidArgumentException) {
                return $this->badFilter('status');
            }
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($this->perPage($request)),
            PurchaseOrderResource::class,
        );
    }

    public function store(StorePurchaseOrderRequest $request, SavePurchaseOrder $action): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $order = $action->create($request->user(), $this->input($request));

        return ApiResponse::data(new PurchaseOrderResource($order), 201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(PurchaseOrder::apiRelations());

        return ApiResponse::data(new PurchaseOrderResource($purchaseOrder));
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        SavePurchaseOrder $action,
    ): JsonResponse {
        $this->authorize('update', $purchaseOrder);

        $order = $action->update($purchaseOrder, $this->input($request));

        return ApiResponse::data(new PurchaseOrderResource($order));
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, SubmitPurchaseOrder $action): JsonResponse
    {
        $this->authorize('submit', $purchaseOrder);

        $order = $action($request->user(), $purchaseOrder, $request);

        return ApiResponse::data(new PurchaseOrderResource($order));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, DecidePurchaseOrder $action): JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $order = $action->approve($request->user(), $purchaseOrder, $request);

        return ApiResponse::data(new PurchaseOrderResource($order));
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, DecidePurchaseOrder $action): JsonResponse
    {
        $this->authorize('reject', $purchaseOrder);

        $order = $action->reject($request->user(), $purchaseOrder, $request);

        return ApiResponse::data(new PurchaseOrderResource($order));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, CancelPurchaseOrder $action): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $order = $action($request->user(), $purchaseOrder, $request);

        return ApiResponse::data(new PurchaseOrderResource($order));
    }

    public function fromAlerts(
        PurchaseOrderFromAlertsRequest $request,
        CreatePurchaseOrdersFromAlerts $action,
    ): JsonResponse {
        $this->authorize('create', PurchaseOrder::class);

        $orders = $action($request->user(), [
            'lines' => $request->validated('lines'),
        ]);

        return ApiResponse::data(PurchaseOrderResource::collection($orders)->resolve(), 201);
    }

    /**
     * @return array{supplier_id: string, destination_warehouse_id: string, expected_on: string|null, notes: string|null, lines: list<array{product_id: string, quantity: int}>}
     */
    private function input(StorePurchaseOrderRequest $request): array
    {
        return [
            'supplier_id' => $request->string('supplier_id')->toString(),
            'destination_warehouse_id' => $request->string('destination_warehouse_id')->toString(),
            'expected_on' => $request->filled('expected_on') ? $request->string('expected_on')->toString() : null,
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
            'lines' => collect($request->validated('lines'))
                ->map(fn (array $line): array => [
                    'product_id' => $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                ])
                ->all(),
        ];
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
