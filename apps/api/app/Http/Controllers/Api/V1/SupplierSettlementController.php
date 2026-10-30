<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SettleSupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRefunds\StoreSupplierRefundRequest;
use App\Http\Requests\SupplierSettlements\StoreSupplierSettlementRequest;
use App\Models\SupplierReturn;
use App\Models\SupplierSettlement;
use Illuminate\Http\JsonResponse;

class SupplierSettlementController extends Controller
{
    public function store(StoreSupplierSettlementRequest $request, SettleSupplier $action): JsonResponse
    {
        $this->authorize('create', SupplierSettlement::class);

        $result = $action->pay(
            $request->user(),
            ['bill_ids' => $request->validated('bill_ids')],
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }

    public function refund(StoreSupplierRefundRequest $request, SettleSupplier $action): JsonResponse
    {
        $this->authorize('refund', SupplierReturn::class);

        $result = $action->receiveRefund(
            $request->user(),
            $request->string('supplier_return_id')->toString(),
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }
}
