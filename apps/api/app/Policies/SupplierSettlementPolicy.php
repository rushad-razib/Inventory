<?php

namespace App\Policies;

use App\Models\User;

class SupplierSettlementPolicy
{
    public function create(User $user): bool
    {
        return $user->can('purchasing.settle');
    }
}
