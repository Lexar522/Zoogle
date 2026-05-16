<?php

use App\Support\CatalogProductsTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $products = CatalogProductsTable::name();

        if (Schema::hasTable($products) && Schema::hasColumn($products, 'locale_content')) {
            Schema::table($products, function (Blueprint $table): void {
                $table->dropColumn('locale_content');
            });
        }

        if (Schema::hasTable('product_care_articles') && Schema::hasColumn('product_care_articles', 'locale_content')) {
            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->dropColumn('locale_content');
            });
        }
    }

    public function down(): void
    {
        $products = CatalogProductsTable::name();

        if (Schema::hasTable($products) && ! Schema::hasColumn($products, 'locale_content')) {
            Schema::table($products, function (Blueprint $table): void {
                $table->json('locale_content')->nullable()->after('description');
            });
        }

        if (Schema::hasTable('product_care_articles') && ! Schema::hasColumn('product_care_articles', 'locale_content')) {
            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->json('locale_content')->nullable()->after('body');
            });
        }
    }
};
