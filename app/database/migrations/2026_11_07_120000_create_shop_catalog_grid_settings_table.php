<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_catalog_grid_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('columns_desktop')->default(4);
            $table->unsignedTinyInteger('columns_tablet')->default(2);
            $table->unsignedTinyInteger('columns_mobile')->default(2);
            $table->string('subtitle_mode', 32)->default('colors_or_category');
            $table->unsignedTinyInteger('title_lines')->default(2);
            $table->string('photo_ratio', 8)->default('1/1');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_catalog_grid_settings');
    }
};
