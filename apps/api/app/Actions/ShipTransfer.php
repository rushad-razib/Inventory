<?php

namespace App\Actions;

use App\Enums\MovementType;
use App\Enums\TransferStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\TransferResource;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferLineLot;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use App\Services\StockLedger;
use Illuminate\Http\Request;

final class ShipTransfer
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly IdempotencyStore $idempotency,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{status: int, data: array<string, mixed>}
     */
    public function __invoke(User $actor, Transfer $transfer, string $key, string $hash, ?Request $request): array
    {
        return $this->idempotency->once(
            $actor,
            'transfer.ship:'.$transfer->id,
            $key,
            $hash,
            function () use ($actor, $transfer, $key, $request) {
                $locked = Transfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== TransferStatus::Draft) {
                    throw new LedgerException('invalid-state', 'Only a draft transfer can be shipped.');
                }

                $lines = $locked->lines()->with('product')->orderBy('product_id')->get();

                foreach ($lines as $line) {
                    $product = $line->product;

                    if (! $product instanceof Product || ! $product->track_stock) {
                        throw new LedgerException('untracked-product', 'This product does not track stock.', 422);
                    }

                    $slices = $this->ledger->consumeOnHand(
                        $locked->source_warehouse_id,
                        $line->product_id,
                        $line->quantity,
                        MovementType::TransferOut,
                        $locked,
                        $actor,
                        $key,
                        false,
                        true,
                    );

                    foreach ($slices as $slice) {
                        TransferLineLot::query()->create([
                            'transfer_line_id' => $line->id,
                            'source_lot_id' => $slice['lot']->id,
                            'supplier_id' => $slice['lot']->supplier_id,
                            'ownership' => $slice['lot']->ownership,
                            'unit_cost' => $slice['lot']->unit_cost,
                            'quantity' => $slice['quantity'],
                        ]);
                    }
                }

                $before = $locked->status->label();
                $locked->status = TransferStatus::InTransit;
                $locked->save();

                $this->audit->write(
                    'transfer.shipped',
                    $locked,
                    $actor,
                    ['status' => $before],
                    ['status' => TransferStatus::InTransit->label()],
                    $request,
                );

                return ['status' => 200, 'data' => $this->payload($locked)];
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Transfer $transfer): array
    {
        $transfer->load([
            'sourceWarehouse',
            'destinationWarehouse',
            'lines.product',
            'lines.lots',
        ]);

        return TransferResource::make($transfer)->resolve(request());
    }
}
