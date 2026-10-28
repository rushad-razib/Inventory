<?php

namespace App\Policies;

use App\Models\Adjustment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.adjust') || $user->can('purchasing.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.adjust');
    }

    public function approve(User $user, Adjustment $adjustment): Response
    {
        if (! $user->can('purchasing.approve')) {
            return Response::deny('You do not have permission to approve adjustments.');
        }

        if ($adjustment->requester_id === $user->id) {
            return Response::deny('You cannot approve your own adjustment.');
        }

        return Response::allow();
    }

    public function reject(User $user, Adjustment $adjustment): Response
    {
        return $this->approve($user, $adjustment);
    }
}
