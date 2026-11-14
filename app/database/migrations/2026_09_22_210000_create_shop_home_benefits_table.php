<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_home_benefits', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('title', 255)->nullable();
            $table->text('lead')->nullable();
            $table->json('items')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_home_benefits');
    }
};
