<?php

namespace App\Actions;

use App\Enums\SupplierReturnStatus;
use App\Exceptions\LedgerException;
use App\Models\SupplierReturn;
use Illuminate\Support\Facades\DB;

final class RejectSupplierReturn
{
    public function __invoke(SupplierReturn $return): SupplierReturn
    {
        return DB::transaction(function () use ($return) {
            $locked = SupplierReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SupplierReturnStatus::Shipped) {
                throw new LedgerException('invalid-state', 'Only a shipped supplier return can be rejected.');
            }

            $locked->status = SupplierReturnStatus::RejectedBySupplier;
            $locked->save();

            return $locked;
        });
    }
}
