<?php

namespace App\Actions;

use App\Enums\SupplierReturnStatus;
use App\Exceptions\LedgerException;
use App\Models\SupplierReturn;
use Illuminate\Support\Facades\DB;

final class ShipSupplierReturn
{
    public function __invoke(SupplierReturn $return): SupplierReturn
    {
        return DB::transaction(function () use ($return) {
            $locked = SupplierReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SupplierReturnStatus::Draft) {
                throw new LedgerException('invalid-state', 'Only a draft supplier return can be shipped.');
            }

            $locked->status = SupplierReturnStatus::Shipped;
            $locked->save();

            return $locked;
        });
    }
}
