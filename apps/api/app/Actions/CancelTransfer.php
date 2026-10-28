<?php

namespace App\Actions;

use App\Enums\TransferStatus;
use App\Exceptions\LedgerException;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyStore;
use Illuminate\Http\Request;

final class CancelTransfer
{
    public function __construct(
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
            'transfer.cancel:'.$transfer->id,
            $key,
            $hash,
            function () use ($actor, $transfer, $request) {
                $locked = Transfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== TransferStatus::Draft) {
                    throw new LedgerException('invalid-state', 'Only a draft transfer can be cancelled.');
                }

                $before = $locked->status->label();
                $locked->status = TransferStatus::Cancelled;
                $locked->save();

                $this->audit->write(
                    'transfer.cancelled',
                    $locked,
                    $actor,
                    ['status' => $before],
                    ['status' => TransferStatus::Cancelled->label()],
                    $request,
                );

                $locked->load([
                    'sourceWarehouse',
                    'destinationWarehouse',
                    'lines.product',
                    'lines.lots',
                ]);

                return [
                    'status' => 200,
                    'data' => TransferResource::make($locked)->resolve(request()),
                ];
            },
        );
    }
}
