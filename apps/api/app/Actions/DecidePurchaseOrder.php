<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\LedgerException;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DecidePurchaseOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(User $actor, PurchaseOrder $order, ?Request $request): PurchaseOrder
    {
        return $this->decide($actor, $order, $request, approved: true);
    }

    public function reject(User $actor, PurchaseOrder $order, ?Request $request): PurchaseOrder
    {
        return $this->decide($actor, $order, $request, approved: false);
    }

    private function decide(User $actor, PurchaseOrder $order, ?Request $request, bool $approved): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order, $request, $approved) {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseOrderStatus::PendingApproval) {
                throw new LedgerException('invalid-state', 'Only a pending purchase order can be decided.');
            }

            if ($locked->requester_id === $actor->id) {
                throw new LedgerException('forbidden', 'You cannot approve your own purchase order.', 403);
            }

            $before = $locked->status->label();
            $locked->status = $approved ? PurchaseOrderStatus::Approved : PurchaseOrderStatus::Rejected;
            $locked->approver_id = $actor->id;
            $locked->save();

            $this->audit->write(
                $approved ? 'purchase_order.approved' : 'purchase_order.rejected',
                $locked,
                $actor,
                ['status' => $before],
                ['status' => $locked->status->label()],
                $request,
            );

            return $locked->load(PurchaseOrder::apiRelations());
        });
    }
}
