<?php

namespace App\Policies;

use App\Models\SupplierBill;
use App\Models\User;

class SupplierBillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.settle') || $user->can('reports.view');
    }

    public function view(User $user, SupplierBill $supplierBill): bool
    {
        return $this->viewAny($user);
    }
}
