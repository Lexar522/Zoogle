<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['animal' => 'Тип тваринки', 'accessory' => 'Тип аксесуару'] as $productType => $name) {
            DB::table('option_groups')->updateOrInsert(
                ['product_type' => $productType, 'slug' => 'type'],
                [
                    'name' => $name,
                    'selection_mode' => 'single',
                    'value_type' => 'text',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('option_groups')
            ->where('slug', 'type')
            ->delete();
    }
};
