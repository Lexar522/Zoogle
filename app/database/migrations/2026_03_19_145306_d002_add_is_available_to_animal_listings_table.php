<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings') || Schema::hasColumn('animal_listings', 'is_available')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->boolean('is_available')->default(true)->after('published_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings') || ! Schema::hasColumn('animal_listings', 'is_available')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropColumn('is_available');
        });
    }
};
