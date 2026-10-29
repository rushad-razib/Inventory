<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.create')
            || $user->can('sales.return')
            || $user->can('reports.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->can('sales.void');
    }
}
