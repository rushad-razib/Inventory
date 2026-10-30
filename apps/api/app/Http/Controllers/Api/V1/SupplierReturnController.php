<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptSupplierReturn;
use App\Actions\OpenSupplierReturn;
use App\Actions\RejectSupplierReturn;
use App\Actions\ShipSupplierReturn;
use App\Enums\SupplierReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierReturns\AcceptSupplierReturnRequest;
use App\Http\Requests\SupplierReturns\StoreSupplierReturnRequest;
use App\Http\Resources\SupplierReturnResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\SupplierReturn;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SupplierReturnController extends Controller
{
    #[QueryParameter('filter[status]', description: 'draft, shipped, accepted, or rejected_by_supplier', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierReturn::class);

        $query = SupplierReturn::query()->with(SupplierReturn::apiRelations());

        if ($request->filled('filter.status')) {
            try {
                $query->where(
                    'status',
                    SupplierReturnStatus::fromLabel((string) $request->input('filter.status')),
                );
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
            SupplierReturnResource::class,
        );
    }

    public function store(StoreSupplierReturnRequest $request, OpenSupplierReturn $action): JsonResponse
    {
        $this->authorize('create', SupplierReturn::class);

        $return = $action($request->user(), [
            'stock_lot_id' => $request->string('stock_lot_id')->toString(),
            'quantity' => $request->integer('quantity'),
        ]);
        $return->load(SupplierReturn::apiRelations());

        return ApiResponse::data(new SupplierReturnResource($return), 201);
    }

    public function show(SupplierReturn $supplierReturn): JsonResponse
    {
        $this->authorize('view', $supplierReturn);

        $supplierReturn->load(SupplierReturn::apiRelations());

        return ApiResponse::data(new SupplierReturnResource($supplierReturn));
    }

    public function ship(SupplierReturn $supplierReturn, ShipSupplierReturn $action): JsonResponse
    {
        $this->authorize('ship', $supplierReturn);

        $return = $action($supplierReturn);
        $return->load(SupplierReturn::apiRelations());

        return ApiResponse::data(new SupplierReturnResource($return));
    }

    public function accept(
        AcceptSupplierReturnRequest $request,
        SupplierReturn $supplierReturn,
        AcceptSupplierReturn $action,
    ): JsonResponse {
        $this->authorize('accept', $supplierReturn);

        $result = $action(
            $request->user(),
            $supplierReturn,
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }

    public function reject(SupplierReturn $supplierReturn, RejectSupplierReturn $action): JsonResponse
    {
        $this->authorize('reject', $supplierReturn);

        $return = $action($supplierReturn);
        $return->load(SupplierReturn::apiRelations());

        return ApiResponse::data(new SupplierReturnResource($return));
    }
}
