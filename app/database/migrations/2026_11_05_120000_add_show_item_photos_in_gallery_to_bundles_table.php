<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->boolean('show_item_photos_in_gallery')->default(false)->after('photos');
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('show_item_photos_in_gallery');
        });
    }
};
