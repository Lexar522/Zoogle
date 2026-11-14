<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Перевірки схеми з пам'яттю процесу. На MySQL кожен Schema::hasTable/hasColumn — запит до information_schema,
 * а моделі каталогу питають це десятки разів на сторінку. Запам'ятовуємо лише «так»: таблицю чи колонку,
 * якої ще немає, міграція може додати в тому ж процесі.
 */
final class SchemaCache
{
    /** @var array<string, true> */
    private static array $tables = [];

    /** @var array<string, true> */
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        if (isset(self::$tables[$table])) {
            return true;
        }

        if (! Schema::hasTable($table)) {
            return false;
        }

        self::$tables[$table] = true;

        return true;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        if (isset(self::$columns[$key])) {
            return true;
        }

        if (! Schema::hasColumn($table, $column)) {
            return false;
        }

        self::$columns[$key] = true;
        self::$tables[$table] = true;

        return true;
    }

    public static function flush(): void
    {
        self::$tables = [];
        self::$columns = [];
    }
}
