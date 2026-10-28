<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.transfer');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.transfer');
    }

    public function ship(User $user, Transfer $transfer): Response
    {
        if (! $user->can('inventory.transfer')) {
            return Response::deny('You do not have permission to ship transfers.');
        }

        if ($user->warehouse_id !== null && $user->warehouse_id !== $transfer->source_warehouse_id) {
            return Response::deny('You can only ship from your pinned warehouse.');
        }

        return Response::allow();
    }

    public function receive(User $user, Transfer $transfer): Response
    {
        if (! $user->can('inventory.transfer')) {
            return Response::deny('You do not have permission to receive transfers.');
        }

        if ($user->warehouse_id !== null && $user->warehouse_id !== $transfer->destination_warehouse_id) {
            return Response::deny('You can only receive into your pinned warehouse.');
        }

        return Response::allow();
    }

    public function cancel(User $user, Transfer $transfer): Response
    {
        if (! $user->can('inventory.transfer')) {
            return Response::deny('You do not have permission to cancel transfers.');
        }

        if ($user->warehouse_id !== null && $user->warehouse_id !== $transfer->source_warehouse_id) {
            return Response::deny('You can only cancel a transfer from your pinned warehouse.');
        }

        return Response::allow();
    }
}
