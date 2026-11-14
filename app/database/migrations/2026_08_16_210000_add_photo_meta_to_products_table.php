<?php

use App\Support\CatalogProductsTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = CatalogProductsTable::name();

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'photo_meta')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->json('photo_meta')->nullable()->after('photos');
        });
    }

    public function down(): void
    {
        $tableName = CatalogProductsTable::name();

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'photo_meta')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('photo_meta');
        });
    }
};
