<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_variants', function (Blueprint $table) {
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
        });

        Schema::table('accessory_variants', function (Blueprint $table) {
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('animal_variants', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });

        Schema::table('accessory_variants', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });
    }
};
