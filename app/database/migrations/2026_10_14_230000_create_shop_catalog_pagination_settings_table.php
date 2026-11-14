<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_catalog_pagination_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('visible_pages')->default(5);
            $table->boolean('show_last_arrow')->default(true);
            $table->boolean('show_all_enabled')->default(true);
            $table->unsignedSmallInteger('per_page')->default(24);
            $table->unsignedInteger('show_all_limit')->default(1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_catalog_pagination_settings');
    }
};
