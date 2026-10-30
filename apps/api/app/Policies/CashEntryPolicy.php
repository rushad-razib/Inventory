<?php

namespace App\Policies;

use App\Models\User;

class CashEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view') || $user->can('purchasing.settle');
    }
}
