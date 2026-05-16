<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_integration_settings')) {
            return;
        }

        if (! Schema::hasColumn('shop_integration_settings', 'google_translation_api_key')) {
            return;
        }

        Schema::table('shop_integration_settings', function (Blueprint $table): void {
            $table->dropColumn('google_translation_api_key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_integration_settings')) {
            return;
        }

        if (Schema::hasColumn('shop_integration_settings', 'google_translation_api_key')) {
            return;
        }

        Schema::table('shop_integration_settings', function (Blueprint $table): void {
            $table->text('google_translation_api_key')->nullable();
        });
    }
};
