<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_integration_settings', function (Blueprint $table): void {
            $table->text('pickup_address')->nullable()->after('google_maps_api_key');
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('pickup_address');
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
        });
    }

    public function down(): void
    {
        Schema::table('shop_integration_settings', function (Blueprint $table): void {
            $table->dropColumn(['pickup_address', 'pickup_lat', 'pickup_lng']);
        });
    }
};
