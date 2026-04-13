<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'animal_variant_id')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('animal_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('animal_variant_id')->nullable()->after('animal_listing_id')->constrained('animal_variants')->nullOnDelete();
        });
    }
};
