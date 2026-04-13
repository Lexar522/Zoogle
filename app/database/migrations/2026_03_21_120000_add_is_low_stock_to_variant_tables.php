<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_variants', function (Blueprint $table): void {
            $table->boolean('is_low_stock')->default(false)->after('is_available');
        });

        Schema::table('accessory_variants', function (Blueprint $table): void {
            $table->boolean('is_low_stock')->default(false)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('animal_variants', function (Blueprint $table): void {
            $table->dropColumn('is_low_stock');
        });

        Schema::table('accessory_variants', function (Blueprint $table): void {
            $table->dropColumn('is_low_stock');
        });
    }
};
