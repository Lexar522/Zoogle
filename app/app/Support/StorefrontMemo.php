<?php

namespace App\Support;

use Closure;

/**
 * Пам'ять у межах запиту для даних вітрини, які сторінка читає по кілька разів (налаштування шапки,
 * інтеграцій, системна група категорій). Скидається, коли відповідну модель зберігають або видаляють.
 */
final class StorefrontMemo
{
    /** @var array<string, mixed> */
    private static array $values = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public static function remember(string $key, Closure $resolve): mixed
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        return self::$values[$key] = $resolve();
    }

    public static function forget(string $key): void
    {
        unset(self::$values[$key]);
    }

    public static function flush(): void
    {
        self::$values = [];
    }
}
