<?php

use App\Support\CatalogProductsTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CatalogProductsTable::name(), function (Blueprint $table): void {
            $table->decimal('length_cm', 8, 2)->nullable()->after('dimensions');
            $table->decimal('width_cm', 8, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 8, 2)->nullable()->after('width_cm');
        });
    }

    public function down(): void
    {
        Schema::table(CatalogProductsTable::name(), function (Blueprint $table): void {
            $table->dropColumn(['length_cm', 'width_cm', 'height_cm']);
        });
    }
};
