<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class CatalogProductVariantsTable
{
    public static function name(): string
    {
        if (Schema::hasTable('product_variants')) {
            return 'product_variants';
        }

        if (Schema::hasTable('animal_variants')) {
            return 'animal_variants';
        }

        return 'product_variants';
    }
}
