<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelTransfer;
use App\Actions\ReceiveTransfer;
use App\Actions\ShipTransfer;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Http\Requests\Transfers\TransferActionRequest;
use App\Http\Resources\TransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transfer::class);

        $query = Transfer::query()->with([
            'sourceWarehouse',
            'destinationWarehouse',
            'lines.product',
            'lines.lots',
        ]);

        if ($request->filled('filter.status')) {
            $query->where('status', TransferStatus::fromLabel($request->string('filter.status')->toString()));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate($this->perPage($request)),
            TransferResource::class,
        );
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = Transfer::query()->create([
            'source_warehouse_id' => $request->string('source_warehouse_id')->toString(),
            'destination_warehouse_id' => $request->string('destination_warehouse_id')->toString(),
            'status' => TransferStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->validated('lines') as $line) {
            $transfer->lines()->create([
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
            ]);
        }

        $transfer->load([
            'sourceWarehouse',
            'destinationWarehouse',
            'lines.product',
            'lines.lots',
        ]);

        return ApiResponse::data(new TransferResource($transfer), 201);
    }

    public function ship(TransferActionRequest $request, Transfer $transfer, ShipTransfer $action): JsonResponse
    {
        $this->authorize('ship', $transfer);

        $result = $action(
            $request->user(),
            $transfer,
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }

    public function receive(TransferActionRequest $request, Transfer $transfer, ReceiveTransfer $action): JsonResponse
    {
        $this->authorize('receive', $transfer);

        $result = $action(
            $request->user(),
            $transfer,
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }

    public function cancel(TransferActionRequest $request, Transfer $transfer, CancelTransfer $action): JsonResponse
    {
        $this->authorize('cancel', $transfer);

        $result = $action(
            $request->user(),
            $transfer,
            $request->idempotencyKey(),
            $request->idempotencyHash(),
            $request,
        );

        return response()->json(['data' => $result['data']], $result['status']);
    }
}
