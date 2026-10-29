<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\LedgerException;
use App\Models\CompanySetting;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmitPurchaseOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, PurchaseOrder $order, ?Request $request): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order, $request) {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new LedgerException('invalid-state', 'Only a draft purchase order can be submitted.');
            }

            if ($locked->lines()->count() < 1) {
                throw new LedgerException('invalid-state', 'A purchase order needs at least one line.', 422);
            }

            $before = $locked->status->label();
            $threshold = CompanySetting::current()->po_approval_threshold;
            $locked->status = $locked->total >= $threshold
                ? PurchaseOrderStatus::PendingApproval
                : PurchaseOrderStatus::Approved;
            $locked->save();

            $this->audit->write(
                'purchase_order.submitted',
                $locked,
                $actor,
                ['status' => $before],
                ['status' => $locked->status->label(), 'total' => $locked->total],
                $request,
            );

            return $locked->load(PurchaseOrder::apiRelations());
        });
    }
}
