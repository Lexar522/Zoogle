<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Список замовлень у Filament (адмінка).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Картка / редагування замовлення в адмінці (Filament EditRecord → policy `update`).
     */
    public function view(User $user, Order $order): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Особистий кабінет: замовлення власника за user_id або той самий e-mail, що в замовленні
     * (для оформлення без входу в акаунт).
     */
    public function viewAccount(User $user, Order $order): bool
    {
        if ($order->user_id !== null) {
            return (int) $order->user_id === (int) $user->id;
        }

        $orderEmail = strtolower(trim((string) $order->customer_email));
        $userEmail = strtolower(trim((string) $user->email));

        return $orderEmail !== '' && $userEmail !== '' && $orderEmail === $userEmail;
    }
}
