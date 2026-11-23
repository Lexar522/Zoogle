<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Таксі-доставка по місту: адреса або точка на карті, без Нової Пошти. */
    public function up(): void
    {
        if (! Schema::hasColumn('shop_integration_settings', 'taxi_delivery_enabled')) {
            Schema::table('shop_integration_settings', function (Blueprint $table): void {
                $table->boolean('taxi_delivery_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_integration_settings', 'taxi_delivery_enabled')) {
            Schema::table('shop_integration_settings', function (Blueprint $table): void {
                $table->dropColumn('taxi_delivery_enabled');
            });
        }
    }
};
