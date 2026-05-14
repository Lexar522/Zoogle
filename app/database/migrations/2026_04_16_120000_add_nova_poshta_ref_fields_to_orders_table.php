<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_city_ref', 36)->nullable()->after('delivery_branch');
            $table->string('delivery_warehouse_ref', 36)->nullable()->after('delivery_city_ref');
            $table->string('delivery_street', 255)->nullable()->after('delivery_warehouse_ref');
            $table->string('delivery_street_ref', 36)->nullable()->after('delivery_street');
            $table->string('delivery_building', 32)->nullable()->after('delivery_street_ref');
            $table->string('delivery_flat', 32)->nullable()->after('delivery_building');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_city_ref',
                'delivery_warehouse_ref',
                'delivery_street',
                'delivery_street_ref',
                'delivery_building',
                'delivery_flat',
            ]);
        });
    }
};
