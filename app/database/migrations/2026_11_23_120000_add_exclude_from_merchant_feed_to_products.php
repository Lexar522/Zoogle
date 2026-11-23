<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Окрема позначка товару «не для Google Merchant»: жива тварина або живий корм у звичайній категорії.
     * На сайті й у sitemap товар лишається.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'exclude_from_merchant_feed')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('exclude_from_merchant_feed')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'exclude_from_merchant_feed')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('exclude_from_merchant_feed');
            });
        }
    }
};
