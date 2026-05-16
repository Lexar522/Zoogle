<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_info_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('slug', 128)->unique();
            $table->string('title_uk', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->string('title_ru', 255)->nullable();
            $table->text('body_uk')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_ru')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_info_pages');
    }
};
