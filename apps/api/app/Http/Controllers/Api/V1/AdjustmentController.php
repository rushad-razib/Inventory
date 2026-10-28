<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PostAdjustment;
use App\Enums\AdjustmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustments\StoreAdjustmentRequest;
use App\Http\Resources\AdjustmentResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\Adjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Adjustment::class);

        $query = Adjustment::query()->with(['warehouse', 'product', 'requester']);

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.status')) {
            try {
                $query->where(
                    'status',
                    AdjustmentStatus::fromLabel($request->string('filter.status')->toString()),
                );
            } catch (\InvalidArgumentException) {
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
            AdjustmentResource::class,
        );
    }

    public function store(StoreAdjustmentRequest $request, PostAdjustment $action): JsonResponse
    {
        $this->authorize('create', Adjustment::class);

        $result = $action->create(
            $request->user(),
            [
                'warehouse_id' => $request->string('warehouse_id')->toString(),
                'product_id' => $request->string('product_id')->toString(),
                'quantity' => $request->integer('quantity'),
                'reason' => $request->string('reason')->toString(),
            ],
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], 201);
    }

    public function approve(Request $request, Adjustment $adjustment, PostAdjustment $action): JsonResponse
    {
        $this->authorize('approve', $adjustment);

        $updated = $action->approve($request->user(), $adjustment, $request);
        $updated->load(['warehouse', 'product', 'requester']);

        return ApiResponse::data(new AdjustmentResource($updated));
    }

    public function reject(Request $request, Adjustment $adjustment, PostAdjustment $action): JsonResponse
    {
        $this->authorize('reject', $adjustment);

        $updated = $action->reject($request->user(), $adjustment, $request);
        $updated->load(['warehouse', 'product', 'requester']);

        return ApiResponse::data(new AdjustmentResource($updated));
    }
}
