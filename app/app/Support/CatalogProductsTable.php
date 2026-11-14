<?php

namespace App\Support;


/**
 * До/після міграції перейменування каталогу фізична таблиця товарів може бути `animal_listings` або `products`.
 */
final class CatalogProductsTable
{
    public static function name(): string
    {
        if (SchemaCache::hasTable('products')) {
            return 'products';
        }

        if (SchemaCache::hasTable('animal_listings')) {
            return 'animal_listings';
        }

        return 'products';
    }
}
