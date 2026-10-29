<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.create')
            || $user->can('purchasing.approve')
            || $user->can('purchasing.receive');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchasing.create');
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchasing.create');
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): Response
    {
        if (! $user->can('purchasing.approve')) {
            return Response::deny('You do not have permission to approve purchase orders.');
        }

        if ($purchaseOrder->requester_id === $user->id) {
            return Response::deny('You cannot approve your own purchase order.');
        }

        return Response::allow();
    }

    public function reject(User $user, PurchaseOrder $purchaseOrder): Response
    {
        return $this->approve($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchasing.create');
    }
}
