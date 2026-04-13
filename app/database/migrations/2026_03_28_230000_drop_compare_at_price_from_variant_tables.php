<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('animal_variants', 'compare_at_price')) {
            Schema::table('animal_variants', function (Blueprint $table): void {
                $table->dropColumn('compare_at_price');
            });
        }

        if (Schema::hasColumn('accessory_variants', 'compare_at_price')) {
            Schema::table('accessory_variants', function (Blueprint $table): void {
                $table->dropColumn('compare_at_price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('animal_variants', 'compare_at_price')) {
            Schema::table('animal_variants', function (Blueprint $table): void {
                $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            });
        }

        if (! Schema::hasColumn('accessory_variants', 'compare_at_price')) {
            Schema::table('accessory_variants', function (Blueprint $table): void {
                $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            });
        }
    }
};
