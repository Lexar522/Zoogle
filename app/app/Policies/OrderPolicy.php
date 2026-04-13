<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAccount(User $user, Order $order): bool
    {
        return $order->user_id !== null && (int) $order->user_id === (int) $user->id;
    }
}
