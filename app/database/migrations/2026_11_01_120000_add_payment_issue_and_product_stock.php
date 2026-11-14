<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_issue')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->text('payment_issue')->nullable();
            });
        }

        if (! Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedInteger('stock_quantity')->nullable();
            });
        }

        // Секретні токени сторінки успіху не мають лишатися в аналітиці.
        if (Schema::hasTable('shop_analytics_events')) {
            DB::table('shop_analytics_events')
                ->where('label', 'like', '/checkout/success/%/%')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('shop_analytics_events')
                            ->where('id', $row->id)
                            ->update(['label' => preg_replace('~^(/checkout/success/\d+)/.*$~', '$1', (string) $row->label)]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_issue')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('payment_issue');
            });
        }

        if (Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('stock_quantity');
            });
        }
    }
};
