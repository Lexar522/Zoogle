<?php

namespace App\Support;

final class CatalogProductVariantsTable
{
    public static function name(): string
    {
        if (SchemaCache::hasTable('product_variants')) {
            return 'product_variants';
        }

        if (SchemaCache::hasTable('animal_variants')) {
            return 'animal_variants';
        }

        return 'product_variants';
    }
}
