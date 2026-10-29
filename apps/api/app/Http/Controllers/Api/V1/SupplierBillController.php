<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SupplierBillStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierBillResource;
use App\Http\Responses\ApiResponse;
use App\Http\Responses\ProblemResponse;
use App\Models\GoodsReceipt;
use App\Models\SaleLineAllocation;
use App\Models\SupplierBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SupplierBillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierBill::class);

        $query = SupplierBill::query()->with('supplier');

        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        if ($request->filled('filter.status')) {
            try {
                $query->where(
                    'status',
                    SupplierBillStatus::fromLabel((string) $request->input('filter.status')),
                );
            } catch (InvalidArgumentException) {
                return $this->badFilter('status');
            }
        }

        if ($request->filled('filter.source_id')) {
            $query->where('source_id', $request->input('filter.source_id'));
        }

        if ($request->filled('filter.source')) {
            $source = (string) $request->input('filter.source');

            $sourceType = match ($source) {
                'goods_receipt' => GoodsReceipt::class,
                'sale_allocation' => SaleLineAllocation::class,
                default => null,
            };

            if ($sourceType === null) {
                return $this->badFilter('source');
            }

            $query->where('source_type', $sourceType);
        }

        return ApiResponse::paginated(
            $query->orderBy('due_on')->paginate($this->perPage($request)),
            SupplierBillResource::class,
        );
    }

    public function show(SupplierBill $supplierBill): JsonResponse
    {
        $this->authorize('view', $supplierBill);

        $supplierBill->load('supplier');

        return ApiResponse::data(new SupplierBillResource($supplierBill));
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
