<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('option_groups')) {
            DB::table('option_groups')
                ->where('product_type', 'animal')
                ->update(['product_type' => 'product']);
        }

        if (Schema::hasTable('animal_listings') && Schema::hasColumn('animal_listings', 'product_type')) {
            DB::table('animal_listings')
                ->where('product_type', 'animal')
                ->update(['product_type' => 'product']);
        }

        $categoryGroupId = Schema::hasTable('option_groups')
            ? (int) (DB::table('option_groups')
                ->where('slug', 'category')
                ->where('product_type', 'product')
                ->value('id') ?? 0)
            : 0;

        // Slug лишаємо «animal»: він може збігатися з product_type гілок каталогу, якщо перейменувати на «product».
        if ($categoryGroupId > 0 && Schema::hasTable('option_values')) {
            DB::table('option_values')
                ->where('option_group_id', $categoryGroupId)
                ->where('slug', 'animal')
                ->update([
                    'name' => 'Товар',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('option_groups')) {
            DB::table('option_groups')
                ->where('product_type', 'product')
                ->update(['product_type' => 'animal']);
        }

        if (Schema::hasTable('animal_listings') && Schema::hasColumn('animal_listings', 'product_type')) {
            DB::table('animal_listings')
                ->where('product_type', 'product')
                ->update(['product_type' => 'animal']);
        }

        $categoryGroupId = Schema::hasTable('option_groups')
            ? (int) (DB::table('option_groups')
                ->where('slug', 'category')
                ->where('product_type', 'animal')
                ->value('id') ?? 0)
            : 0;

        if ($categoryGroupId > 0 && Schema::hasTable('option_values')) {
            DB::table('option_values')
                ->where('option_group_id', $categoryGroupId)
                ->where('slug', 'animal')
                ->where('name', 'Товар')
                ->update([
                    'name' => 'Тваринка',
                    'updated_at' => now(),
                ]);
        }
    }
};
