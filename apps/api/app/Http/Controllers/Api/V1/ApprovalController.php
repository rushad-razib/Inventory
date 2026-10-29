<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdjustmentStatus;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApprovalsResource;
use App\Http\Responses\ApiResponse;
use App\Models\Adjustment;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('purchasing.approve') && ! $user->can('sales.refund.approve')) {
            return $this->forbidden('You do not have permission to view approvals.');
        }

        $orders = collect();
        $adjustments = collect();

        if ($user->can('purchasing.approve')) {
            $orders = PurchaseOrder::query()
                ->with(PurchaseOrder::apiRelations())
                ->where('status', PurchaseOrderStatus::PendingApproval)
                ->where('requester_id', '!=', $user->id)
                ->orderBy('created_at')
                ->get();

            $adjustments = Adjustment::query()
                ->with(['warehouse', 'product', 'requester'])
                ->where('status', AdjustmentStatus::PendingApproval)
                ->where('requester_id', '!=', $user->id)
                ->orderBy('created_at')
                ->get();
        }

        return ApiResponse::data(new ApprovalsResource([
            'purchase_orders' => $orders,
            'adjustments' => $adjustments,
        ]));
    }
}
