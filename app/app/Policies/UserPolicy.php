<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Список покупців містить персональні дані — лише адмін.
    public function viewAny(User $auth): bool
    {
        return $auth->hasRole('admin');
    }

    public function view(User $auth, User $target): bool
    {
        return $auth->hasRole('admin');
    }

    public function create(User $auth): bool
    {
        return $auth->hasRole('admin');
    }

    public function update(User $auth, User $target): bool
    {
        return $auth->hasRole('admin');
    }

    public function delete(User $auth, User $target): bool
    {
        if ((int) $auth->id === (int) $target->id) {
            return false;
        }

        return $auth->hasRole('admin');
    }
}
