<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DecideCustomerReturn;
use App\Actions\RefundCustomer;
use App\Actions\RequestCustomerReturn;
use App\Enums\CustomerReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerReturns\RefundCustomerReturnRequest;
use App\Http\Requests\CustomerReturns\StoreCustomerReturnRequest;
use App\Http\Resources\CustomerReturnResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\CustomerReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerReturn::class);

        $query = CustomerReturn::query()->with(CustomerReturn::apiRelations());

        if ($request->filled('filter.sale_id')) {
            $query->where('sale_id', $request->input('filter.sale_id'));
        }

        if ($request->filled('filter.status')) {
            try {
                $query->where(
                    'status',
                    CustomerReturnStatus::fromLabel((string) $request->input('filter.status')),
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
            CustomerReturnResource::class,
        );
    }

    public function show(CustomerReturn $customerReturn): JsonResponse
    {
        $this->authorize('view', $customerReturn);

        $customerReturn->load(CustomerReturn::apiRelations());

        return ApiResponse::data(new CustomerReturnResource($customerReturn));
    }

    public function store(StoreCustomerReturnRequest $request, RequestCustomerReturn $action): JsonResponse
    {
        $this->authorize('create', CustomerReturn::class);

        $payload = $action(
            $request->user(),
            [
                'sale_id' => $request->string('sale_id')->toString(),
                'lines' => collect($request->validated('lines'))
                    ->map(fn (array $line): array => [
                        'sale_line_id' => $line['sale_line_id'],
                        'quantity' => (int) $line['quantity'],
                        'reason' => $line['reason'],
                    ])
                    ->all(),
            ],
            $request,
        );

        return ApiResponse::data($payload, 201);
    }

    public function approve(Request $request, CustomerReturn $customerReturn, DecideCustomerReturn $action): JsonResponse
    {
        $this->authorize('approve', $customerReturn);

        return ApiResponse::data($action($request->user(), $customerReturn, true, $request));
    }

    public function reject(Request $request, CustomerReturn $customerReturn, DecideCustomerReturn $action): JsonResponse
    {
        $this->authorize('reject', $customerReturn);

        return ApiResponse::data($action($request->user(), $customerReturn, false, $request));
    }

    public function refund(
        RefundCustomerReturnRequest $request,
        CustomerReturn $customerReturn,
        RefundCustomer $action,
    ): JsonResponse {
        $this->authorize('refund', $customerReturn);

        $result = $action(
            $request->user(),
            $customerReturn,
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }
}
