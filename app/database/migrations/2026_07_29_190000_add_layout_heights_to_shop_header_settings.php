<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_header_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('featured_panel_max_height_px')
                ->nullable()
                ->after('featured_right_option_value_id');
            $table->unsignedSmallInteger('banner_height_px')
                ->nullable()
                ->after('featured_panel_max_height_px');
        });
    }

    public function down(): void
    {
        Schema::table('shop_header_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'featured_panel_max_height_px',
                'banner_height_px',
            ]);
        });
    }
};
