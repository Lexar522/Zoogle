<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('animal_listings', function (Blueprint $table) {
            $table->json('variant_options')->nullable()->after('photos');
        });

        Schema::table('accessory_listings', function (Blueprint $table) {
            $table->json('variant_options')->nullable()->after('photos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animal_listings', function (Blueprint $table) {
            $table->dropColumn('variant_options');
        });

        Schema::table('accessory_listings', function (Blueprint $table) {
            $table->dropColumn('variant_options');
        });
    }
};
