<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings') || Schema::hasTable('products')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasColumn('order_items', 'animal_listing_id')) {
                Schema::table('order_items', function (Blueprint $table): void {
                    $table->dropForeign(['animal_listing_id']);
                });
            }

            Schema::table('animal_variants', function (Blueprint $table): void {
                $table->dropForeign(['animal_listing_id']);
            });

            Schema::rename('animal_listings', 'products');
            Schema::rename('animal_variants', 'product_variants');

            Schema::table('product_variants', function (Blueprint $table): void {
                $table->renameColumn('animal_listing_id', 'product_id');
            });

            Schema::table('order_items', function (Blueprint $table): void {
                $table->renameColumn('animal_listing_id', 'product_id');
            });

            Schema::table('product_variants', function (Blueprint $table): void {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });

            Schema::table('order_items', function (Blueprint $table): void {
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });

            if (Schema::hasTable('promotion_targets')) {
                DB::table('promotion_targets')->where('target_type', 'animal_listing')->update(['target_type' => 'product']);
                DB::table('promotion_targets')->where('target_type', 'animal_variant')->update(['target_type' => 'product_variant']);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || Schema::hasTable('animal_listings')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable('promotion_targets')) {
                DB::table('promotion_targets')->where('target_type', 'product')->update(['target_type' => 'animal_listing']);
                DB::table('promotion_targets')->where('target_type', 'product_variant')->update(['target_type' => 'animal_variant']);
            }

            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
            });

            Schema::table('product_variants', function (Blueprint $table): void {
                $table->dropForeign(['product_id']);
            });

            Schema::table('order_items', function (Blueprint $table): void {
                $table->renameColumn('product_id', 'animal_listing_id');
            });

            Schema::table('product_variants', function (Blueprint $table): void {
                $table->renameColumn('product_id', 'animal_listing_id');
            });

            Schema::rename('products', 'animal_listings');
            Schema::rename('product_variants', 'animal_variants');

            Schema::table('animal_variants', function (Blueprint $table): void {
                $table->foreign('animal_listing_id')->references('id')->on('animal_listings')->cascadeOnDelete();
            });

            Schema::table('order_items', function (Blueprint $table): void {
                $table->foreign('animal_listing_id')->references('id')->on('animal_listings')->nullOnDelete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
