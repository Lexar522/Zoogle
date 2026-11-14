<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'inventory_committed')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->boolean('inventory_committed')->default(false)->after('payment_status');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'variant_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'saved_cart')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('saved_cart')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'inventory_committed')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('inventory_committed');
            });
        }

        if (Schema::hasColumn('order_items', 'variant_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('variant_id');
            });
        }

        if (Schema::hasColumn('users', 'saved_cart')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('saved_cart');
            });
        }
    }
};
