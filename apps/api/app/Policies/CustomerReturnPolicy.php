<?php

namespace App\Policies;

use App\Models\CustomerReturn;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.return') || $user->can('sales.refund.approve');
    }

    public function view(User $user, CustomerReturn $customerReturn): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('sales.return');
    }

    public function approve(User $user, CustomerReturn $customerReturn): Response
    {
        if (! $user->can('sales.refund.approve')) {
            return Response::deny('You do not have permission to approve customer returns.');
        }

        if ($customerReturn->requester_id === $user->id) {
            return Response::deny('You cannot approve your own customer return.');
        }

        return Response::allow();
    }

    public function reject(User $user, CustomerReturn $customerReturn): Response
    {
        return $this->approve($user, $customerReturn);
    }

    public function refund(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->can('sales.return');
    }
}
