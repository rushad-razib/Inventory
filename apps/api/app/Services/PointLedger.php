<?php

namespace App\Services;

use App\Enums\PointEntryType;
use App\Models\Customer;
use App\Models\CustomerPointEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class PointLedger
{
    public function post(Customer $customer, PointEntryType $type, int $delta, Model $source, User $actor): void
    {
        if ($delta === 0) {
            return;
        }

        $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

        CustomerPointEntry::query()->create([
            'customer_id' => $locked->id,
            'type' => $type,
            'points' => $delta,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);

        $locked->points_balance += $delta;
        $locked->save();
        $customer->points_balance = $locked->points_balance;
    }
}
