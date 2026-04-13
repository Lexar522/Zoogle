<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $auth, User $target): bool
    {
        return $auth->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $auth): bool
    {
        return $auth->hasAnyRole(['admin', 'manager']);
    }

    public function update(User $auth, User $target): bool
    {
        return $auth->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $auth, User $target): bool
    {
        if ((int) $auth->id === (int) $target->id) {
            return false;
        }

        return $auth->hasAnyRole(['admin', 'manager']);
    }
}
