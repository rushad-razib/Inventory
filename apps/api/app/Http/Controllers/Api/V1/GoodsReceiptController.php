<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReceiveGoods;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceipts\StoreGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Http\Responses\ApiResponse;
use App\Models\GoodsReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $query = GoodsReceipt::query()->with(GoodsReceipt::apiRelations());

        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        if ($request->filled('filter.supplier_id')) {
            $query->where('supplier_id', $request->input('filter.supplier_id'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($this->perPage($request)),
            GoodsReceiptResource::class,
        );
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('view', $goodsReceipt);

        $goodsReceipt->load(GoodsReceipt::apiRelations());

        return ApiResponse::data(new GoodsReceiptResource($goodsReceipt));
    }

    public function store(StoreGoodsReceiptRequest $request, ReceiveGoods $action): JsonResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        $result = $action(
            $request->user(),
            [
                'purchase_order_id' => $request->filled('purchase_order_id')
                    ? $request->string('purchase_order_id')->toString()
                    : null,
                'supplier_id' => $request->filled('supplier_id')
                    ? $request->string('supplier_id')->toString()
                    : null,
                'warehouse_id' => $request->filled('warehouse_id')
                    ? $request->string('warehouse_id')->toString()
                    : null,
                'lines' => collect($request->validated('lines'))
                    ->map(fn (array $line): array => [
                        'product_id' => $line['product_id'],
                        'quantity' => (int) $line['quantity'],
                    ])
                    ->all(),
            ],
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }
}
