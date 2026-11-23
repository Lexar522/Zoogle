<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Хто модерує відгуки й питання покупців: ті самі ролі, що мають доступ до адмінки.
 */
final class StorefrontModerators
{
    /** @var list<string> */
    public const ROLES = ['admin', 'manager'];

    public static function allows(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ROLES);
    }

    /**
     * @return Collection<int, User>
     */
    public static function all(): Collection
    {
        return User::role(self::ROLES)->get();
    }
}
