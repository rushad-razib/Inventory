<?php

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\LedgerException;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CancelPurchaseOrder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, PurchaseOrder $order, ?Request $request): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $order, $request) {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $cancellable = in_array($locked->status, [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::PendingApproval,
                PurchaseOrderStatus::Approved,
            ], true);

            if (! $cancellable) {
                throw new LedgerException('invalid-state', 'This purchase order cannot be cancelled.');
            }

            if ((int) $locked->lines()->sum('quantity_received') > 0) {
                throw new LedgerException('invalid-state', 'A purchase order cannot be cancelled after a receipt.');
            }

            $before = $locked->status->label();
            $locked->status = PurchaseOrderStatus::Cancelled;
            $locked->save();

            $this->audit->write(
                'purchase_order.cancelled',
                $locked,
                $actor,
                ['status' => $before],
                ['status' => PurchaseOrderStatus::Cancelled->label()],
                $request,
            );

            return $locked->load(PurchaseOrder::apiRelations());
        });
    }
}
