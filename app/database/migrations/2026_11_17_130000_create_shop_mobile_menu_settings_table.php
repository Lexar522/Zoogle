<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_mobile_menu_settings', function (Blueprint $table) {
            $table->id();
            $table->json('items')->nullable();
            $table->boolean('show_contacts')->default(true);
            $table->boolean('show_login')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_mobile_menu_settings');
    }
};
