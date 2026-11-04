<?php

namespace App\Actions;

use App\Enums\CashDirection;
use App\Enums\CashEntryType;
use App\Enums\CashLocation;
use App\Exceptions\LedgerException;
use App\Models\CashEntry;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CashBalances;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdjustCash
{
    public function __construct(
        private readonly CashBalances $balances,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(
        User $actor,
        CashLocation $location,
        CashDirection $direction,
        int $amount,
        string $note,
        ?Request $request = null,
    ): CashEntry {
        if (! in_array($location, [CashLocation::Cash, CashLocation::Bank], true)) {
            throw new LedgerException('invalid-state', 'Only main cash and the bank can be adjusted.', 422);
        }

        if ($amount < 1) {
            throw new LedgerException('invalid-state', 'The amount must be at least 1.', 422);
        }

        return DB::transaction(function () use ($actor, $location, $direction, $amount, $note, $request): CashEntry {
            CompanySetting::query()->lockForUpdate()->first();

            $before = $this->balances->balance($location);
            if ($location === CashLocation::Cash) {
                $before += $this->balances->balance(CashLocation::Till);
            }

            if ($direction === CashDirection::Out && $before < $amount) {
                throw new LedgerException('invalid-state', 'That withdrawal is larger than the balance.', 422);
            }

            $entry = CashEntry::query()->create([
                'location' => $location,
                'direction' => $direction,
                'type' => CashEntryType::CashAdjustment,
                'amount' => $amount,
                'source_type' => $actor->getMorphClass(),
                'source_id' => $actor->id,
                'actor_id' => $actor->id,
                'occurred_at' => now(),
            ]);

            $after = $direction === CashDirection::In ? $before + $amount : $before - $amount;

            $this->audit->write('cash.adjusted', $entry, $actor, [
                'location' => $location->label(),
                'balance' => $before,
            ], [
                'location' => $location->label(),
                'direction' => $direction->label(),
                'amount' => $amount,
                'note' => $note,
                'balance' => $after,
            ], $request);

            return $entry;
        });
    }
}
