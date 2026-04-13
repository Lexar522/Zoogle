<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create (or reuse) one system group "category".
        DB::table('option_groups')->updateOrInsert(
            ['product_type' => 'animal', 'slug' => 'category'],
            [
                'name' => 'Категорія',
                'selection_mode' => 'single',
                'value_type' => 'text',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $categoryGroupId = DB::table('option_groups')
            ->where('product_type', 'animal')
            ->where('slug', 'category')
            ->value('id');

        if (! $categoryGroupId) {
            return;
        }

        DB::table('option_values')->updateOrInsert(
            ['option_group_id' => $categoryGroupId, 'slug' => 'animal'],
            [
                'name' => 'Тваринка',
                'sort_order' => 1,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('option_values')->updateOrInsert(
            ['option_group_id' => $categoryGroupId, 'slug' => 'accessory'],
            [
                'name' => 'Аксесуар',
                'sort_order' => 2,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Remove old duplicated system groups "type".
        $oldTypeGroupIds = DB::table('option_groups')
            ->where('slug', 'type')
            ->pluck('id')
            ->all();

        if ($oldTypeGroupIds !== []) {
            DB::table('option_values')->whereIn('option_group_id', $oldTypeGroupIds)->delete();
            DB::table('option_groups')->whereIn('id', $oldTypeGroupIds)->delete();
        }
    }

    public function down(): void
    {
        DB::table('option_groups')->where('slug', 'category')->delete();
    }
};
