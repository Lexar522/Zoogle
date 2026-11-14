<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_header_settings', function (Blueprint $table): void {
            $table->json('chip_styles')->nullable()->after('banner_height_px');
        });
    }

    public function down(): void
    {
        Schema::table('shop_header_settings', function (Blueprint $table): void {
            $table->dropColumn('chip_styles');
        });
    }
};
