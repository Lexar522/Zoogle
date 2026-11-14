<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_header_banners', function (Blueprint $table): void {
            $table->string('text_uk', 512)->nullable()->after('alt_ru');
            $table->string('text_en', 512)->nullable()->after('text_uk');
            $table->string('text_ru', 512)->nullable()->after('text_en');
        });
    }

    public function down(): void
    {
        Schema::table('shop_header_banners', function (Blueprint $table): void {
            $table->dropColumn(['text_uk', 'text_en', 'text_ru']);
        });
    }
};
