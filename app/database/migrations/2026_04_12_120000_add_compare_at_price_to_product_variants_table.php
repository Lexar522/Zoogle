<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        if (! Schema::hasColumn('product_variants', 'compare_at_price')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_variants') || ! Schema::hasColumn('product_variants', 'compare_at_price')) {
            return;
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });
    }
};
