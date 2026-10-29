<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.receive') || $user->can('inventory.view');
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.receive');
    }
}
