<?php

namespace App\Actions;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\MovementType;
use App\Exceptions\LedgerException;
use App\Http\Resources\AdjustmentResource;
use App\Models\Adjustment;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use App\Services\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PostAdjustment
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{warehouse_id: string, product_id: string, quantity: int, reason: string}  $input
     * @return array{status: int, data: array<string, mixed>}
     */
    public function create(User $actor, array $input, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once($actor, 'adjustment.create', $key, $hash, function () use ($actor, $input, $key, $request) {
            $product = $this->trackedProduct($input['product_id']);
            $this->assertWarehousePin($actor, $input['warehouse_id']);

            $quantity = (int) $input['quantity'];
            $reason = AdjustmentReason::fromLabel($input['reason']);

            if ($reason === AdjustmentReason::Damage && $quantity < 1) {
                throw new LedgerException('invalid-state', 'Damage quantity must be positive.', 422);
            }

            $threshold = CompanySetting::current()->adjustment_approval_threshold;
            $pending = abs($quantity) >= $threshold;

            $adjustment = Adjustment::query()->create([
                'warehouse_id' => $input['warehouse_id'],
                'product_id' => $product->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'status' => $pending ? AdjustmentStatus::PendingApproval : AdjustmentStatus::Posted,
                'requester_id' => $actor->id,
                'posted_at' => $pending ? null : now(),
            ]);

            if (! $pending) {
                $this->apply($adjustment, $actor, $key);
                $this->audit->write(
                    'adjustment.posted',
                    $adjustment,
                    $actor,
                    null,
                    ['status' => AdjustmentStatus::Posted->label(), 'quantity' => $quantity],
                    $request,
                );
            }

            return ['status' => 201, 'data' => $this->payload($adjustment)];
        });
    }

    public function approve(User $actor, Adjustment $adjustment, ?Request $request): Adjustment
    {
        return DB::transaction(function () use ($actor, $adjustment, $request) {
            $locked = Adjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AdjustmentStatus::PendingApproval) {
                throw new LedgerException('invalid-state', 'Only a pending adjustment can be approved.');
            }

            $before = $locked->status->label();
            $this->apply($locked, $actor, null);
            $locked->status = AdjustmentStatus::Posted;
            $locked->approver_id = $actor->id;
            $locked->posted_at = now();
            $locked->save();

            $this->audit->write(
                'adjustment.approved',
                $locked,
                $actor,
                ['status' => $before],
                ['status' => AdjustmentStatus::Posted->label()],
                $request,
            );

            return $locked->refresh();
        });
    }

    public function reject(User $actor, Adjustment $adjustment, ?Request $request): Adjustment
    {
        return DB::transaction(function () use ($actor, $adjustment, $request) {
            $locked = Adjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AdjustmentStatus::PendingApproval) {
                throw new LedgerException('invalid-state', 'Only a pending adjustment can be rejected.');
            }

            $before = $locked->status->label();
            $locked->status = AdjustmentStatus::Rejected;
            $locked->approver_id = $actor->id;
            $locked->save();

            $this->audit->write(
                'adjustment.rejected',
                $locked,
                $actor,
                ['status' => $before],
                ['status' => AdjustmentStatus::Rejected->label()],
                $request,
            );

            return $locked->refresh();
        });
    }

    private function apply(Adjustment $adjustment, User $actor, ?string $idempotencyKey): void
    {
        $quantity = $adjustment->quantity;

        if ($adjustment->reason === AdjustmentReason::Damage || $quantity < 0) {
            $this->ledger->consumeOnHand(
                $adjustment->warehouse_id,
                $adjustment->product_id,
                abs($quantity),
                MovementType::Adjustment,
                $adjustment,
                $actor,
                $idempotencyKey,
                $adjustment->reason === AdjustmentReason::Damage,
                false,
            );

            return;
        }

        $this->ledger->increaseOnHand(
            $adjustment->warehouse_id,
            $adjustment->product_id,
            $quantity,
            $adjustment,
            $actor,
            $idempotencyKey,
        );
    }

    private function trackedProduct(string $productId): Product
    {
        $product = Product::query()->findOrFail($productId);

        if (! $product->track_stock) {
            throw new LedgerException('untracked-product', 'This product does not track stock.', 422);
        }

        return $product;
    }

    private function assertWarehousePin(User $actor, string $warehouseId): void
    {
        if ($actor->warehouse_id !== null && $actor->warehouse_id !== $warehouseId) {
            throw new LedgerException(
                'forbidden',
                'You can only adjust stock in your pinned warehouse.',
                403,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Adjustment $adjustment): array
    {
        $adjustment->load(['product', 'warehouse', 'requester']);

        return AdjustmentResource::make($adjustment)->resolve(request());
    }
}
