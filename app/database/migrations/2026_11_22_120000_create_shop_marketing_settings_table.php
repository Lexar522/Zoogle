<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_marketing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('ga4_id', 32)->nullable();
            $table->string('meta_pixel_id', 32)->nullable();
            $table->string('google_site_verification', 120)->nullable();
            $table->string('facebook_domain_verification', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_marketing_settings');
    }
};
