<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * До/після міграції перейменування каталогу фізична таблиця товарів може бути `animal_listings` або `products`.
 */
final class CatalogProductsTable
{
    public static function name(): string
    {
        if (Schema::hasTable('products')) {
            return 'products';
        }

        if (Schema::hasTable('animal_listings')) {
            return 'animal_listings';
        }

        return 'products';
    }
}
