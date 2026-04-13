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

        if (! Schema::hasColumn('animal_listings', 'photos')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropColumn('photos');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        if (Schema::hasColumn('animal_listings', 'photos')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->json('photos')->nullable()->after('search_tags');
        });
    }
};
