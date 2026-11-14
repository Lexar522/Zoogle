<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_home_product_sections', function (Blueprint $table) {
            $table->id();
            $table->boolean('hits_enabled')->default(true);
            $table->string('hits_title', 255)->nullable();
            $table->text('hits_lead')->nullable();
            $table->boolean('recommended_enabled')->default(true);
            $table->string('recommended_title', 255)->nullable();
            $table->text('recommended_lead')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_home_product_sections');
    }
};
