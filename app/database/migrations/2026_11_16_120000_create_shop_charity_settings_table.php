<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_charity_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('header_label', 40)->nullable();
            $table->string('title', 160)->nullable();
            $table->longText('body')->nullable();
            $table->string('jar_url', 500)->nullable();
            $table->string('button_label', 60)->nullable();
            $table->string('order_prompt', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_charity_settings');
    }
};
