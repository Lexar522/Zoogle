<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $bundleAfterColumn = Schema::hasColumn('order_items', 'product_id')
            ? 'product_id'
            : (Schema::hasColumn('order_items', 'animal_listing_id') ? 'animal_listing_id' : 'order_id');
        $snapshotAfterColumn = Schema::hasColumn('order_items', 'option_value_ids')
            ? 'option_value_ids'
            : 'title_snapshot';

        if (! Schema::hasColumn('order_items', 'bundle_id')) {
            Schema::table('order_items', function (Blueprint $table) use ($bundleAfterColumn): void {
                $table->foreignId('bundle_id')
                    ->nullable()
                    ->after($bundleAfterColumn)
                    ->constrained('bundles')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('order_items', 'bundle_snapshot')) {
            Schema::table('order_items', function (Blueprint $table) use ($snapshotAfterColumn): void {
                $table->json('bundle_snapshot')
                    ->nullable()
                    ->after($snapshotAfterColumn);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'bundle_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropForeign(['bundle_id']);
                $table->dropColumn('bundle_id');
            });
        }

        if (Schema::hasColumn('order_items', 'bundle_snapshot')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('bundle_snapshot');
            });
        }
    }
};
