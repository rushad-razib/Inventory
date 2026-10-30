<?php

namespace App\Policies;

use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupplierReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier_returns.manage') || $user->can('purchasing.settle');
    }

    public function view(User $user, SupplierReturn $supplierReturn): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('supplier_returns.manage');
    }

    public function ship(User $user, SupplierReturn $supplierReturn): Response
    {
        if (! $user->can('supplier_returns.manage')) {
            return Response::deny('You do not have permission to ship supplier returns.');
        }

        $supplierReturn->loadMissing('lot');

        if ($user->warehouse_id !== null && $user->warehouse_id !== $supplierReturn->lot->warehouse_id) {
            return Response::deny('You can only ship a supplier return from your pinned warehouse.');
        }

        return Response::allow();
    }

    public function accept(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can('purchasing.settle');
    }

    public function reject(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can('purchasing.settle');
    }

    public function refund(User $user): bool
    {
        return $user->can('purchasing.settle');
    }
}
