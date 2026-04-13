<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('animal_variants')->whereNull('is_visible')->update(['is_visible' => true]);
        DB::table('animal_variants')->whereNull('is_sold')->update(['is_sold' => false]);
        DB::table('accessory_variants')->whereNull('is_visible')->update(['is_visible' => true]);
    }

    public function down(): void
    {
        // no-op
    }
};
