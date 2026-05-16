<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_footer_brands', function (Blueprint $table) {
            $table->id();
            $table->text('body')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('site_title', 128)->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->timestamps();
        });

        Schema::create('shop_footer_columns', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title_uk', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->string('title_ru', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('shop_footer_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_footer_column_id')
                ->constrained('shop_footer_columns')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('label_uk', 255)->nullable();
            $table->string('label_en', 255)->nullable();
            $table->string('label_ru', 255)->nullable();
            $table->string('url', 2048);
            $table->boolean('open_new_tab')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_footer_links');
        Schema::dropIfExists('shop_footer_columns');
        Schema::dropIfExists('shop_footer_brands');
    }
};
