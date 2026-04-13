<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('success_token')->nullable()->after('total');
        });

        DB::table('orders')
            ->whereNull('success_token')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['success_token' => Str::uuid()->toString()]);
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('success_token')->nullable(false)->change();
            $table->unique('success_token');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('animal_variant_id')->nullable()->after('animal_listing_id')->constrained('animal_variants')->nullOnDelete();
            $table->json('option_value_ids')->nullable()->after('title_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('animal_variant_id');
            $table->dropColumn('option_value_ids');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['success_token']);
            $table->dropColumn('success_token');
        });
    }
};
