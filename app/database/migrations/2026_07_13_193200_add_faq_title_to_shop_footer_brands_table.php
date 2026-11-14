<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_footer_brands', function (Blueprint $table) {
            $table->string('faq_title_uk', 255)->nullable()->after('logo_path');
            $table->string('faq_title_en', 255)->nullable()->after('faq_title_uk');
            $table->string('faq_title_ru', 255)->nullable()->after('faq_title_en');
        });
    }

    public function down(): void
    {
        Schema::table('shop_footer_brands', function (Blueprint $table) {
            $table->dropColumn(['faq_title_uk', 'faq_title_en', 'faq_title_ru']);
        });
    }
};
