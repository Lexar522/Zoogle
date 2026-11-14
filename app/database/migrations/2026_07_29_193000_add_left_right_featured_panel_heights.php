<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_header_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('featured_left_panel_max_height_px')
                ->nullable()
                ->after('featured_panel_max_height_px');
            $table->unsignedSmallInteger('featured_right_panel_max_height_px')
                ->nullable()
                ->after('featured_left_panel_max_height_px');
        });

        if (Schema::hasColumn('shop_header_settings', 'featured_panel_max_height_px')) {
            DB::table('shop_header_settings')->update([
                'featured_left_panel_max_height_px' => DB::raw('featured_panel_max_height_px'),
                'featured_right_panel_max_height_px' => DB::raw('featured_panel_max_height_px'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('shop_header_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'featured_left_panel_max_height_px',
                'featured_right_panel_max_height_px',
            ]);
        });
    }
};
