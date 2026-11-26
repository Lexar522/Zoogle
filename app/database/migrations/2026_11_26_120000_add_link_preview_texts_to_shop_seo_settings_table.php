<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Окремий текст картки посилання в Telegram, Viber, Facebook (og:title / og:description).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_seo_settings', function (Blueprint $table) {
            $table->string('home_og_title', 120)->nullable()->after('home_description');
            $table->string('home_og_description', 300)->nullable()->after('home_og_title');
        });
    }

    public function down(): void
    {
        Schema::table('shop_seo_settings', function (Blueprint $table) {
            $table->dropColumn(['home_og_title', 'home_og_description']);
        });
    }
};
