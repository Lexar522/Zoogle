<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $productsTable = Schema::hasTable('products')
            ? 'products'
            : (Schema::hasTable('animal_listings') ? 'animal_listings' : 'products');

        if (! Schema::hasTable('product_recommendations')) {
            Schema::create('product_recommendations', function (Blueprint $table) use ($productsTable): void {
                $table->id();
                $table->foreignId('product_id')->constrained($productsTable)->cascadeOnDelete();
                $table->foreignId('recommended_product_id')->constrained($productsTable)->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'recommended_product_id'], 'product_recommendations_unique');
                $table->index(['product_id', 'sort_order']);
            });
        }

        if (Schema::hasTable($productsTable) && Schema::hasColumn($productsTable, 'search_tags')) {
            Schema::table($productsTable, function (Blueprint $table): void {
                $table->dropColumn('search_tags');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recommendations');

        $productsTable = Schema::hasTable('products')
            ? 'products'
            : (Schema::hasTable('animal_listings') ? 'animal_listings' : 'products');

        if (Schema::hasTable($productsTable) && ! Schema::hasColumn($productsTable, 'search_tags')) {
            Schema::table($productsTable, function (Blueprint $table): void {
                $table->json('search_tags')->nullable();
            });
        }
    }
};
