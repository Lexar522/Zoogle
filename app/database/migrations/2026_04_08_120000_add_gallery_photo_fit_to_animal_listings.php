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

        if (Schema::hasColumn('animal_listings', 'gallery_photo_fit')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->string('gallery_photo_fit', 20)->default('cover')->after('photos');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('animal_listings') && Schema::hasColumn('animal_listings', 'gallery_photo_fit')) {
            Schema::table('animal_listings', function (Blueprint $table): void {
                $table->dropColumn('gallery_photo_fit');
            });
        }
    }
};
