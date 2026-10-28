<?php

namespace App\Actions;

use App\Enums\TransferStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use App\Services\StockLedger;
use Illuminate\Http\Request;

final class ReceiveTransfer
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
            'transfer.receive:'.$transfer->id,
            $key,
            $hash,
            function () use ($actor, $transfer, $key, $request) {
                $locked = Transfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== TransferStatus::InTransit) {
                    throw new LedgerException('invalid-state', 'Only an in-transit transfer can be received.');
                }

                $lines = $locked->lines()->with('lots')->orderBy('product_id')->get();

                foreach ($lines as $line) {
                    foreach ($line->lots as $slice) {
                        $destination = $this->ledger->receiveSlice(
                            $locked->source_warehouse_id,
                            $locked->destination_warehouse_id,
                            $line->product_id,
                            $slice->supplier_id,
                            $slice->ownership,
                            $slice->unit_cost,
                            $slice->quantity,
                            $locked,
                            $actor,
                            $key,
                        );

                        $slice->destination_lot_id = $destination->id;
                        $slice->save();
                    }
                }

                $before = $locked->status->label();
                $locked->status = TransferStatus::Received;
                $locked->save();

                $this->audit->write(
                    'transfer.received',
                    $locked,
                    $actor,
                    ['status' => $before],
                    ['status' => TransferStatus::Received->label()],
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
