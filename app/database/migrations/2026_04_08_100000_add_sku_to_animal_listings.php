<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        if (Schema::hasColumn('animal_listings', 'sku')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->string('sku', 120)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('animal_listings') && Schema::hasColumn('animal_listings', 'sku')) {
            Schema::table('animal_listings', function (Blueprint $table): void {
                $table->dropColumn('sku');
            });
        }
    }
};
