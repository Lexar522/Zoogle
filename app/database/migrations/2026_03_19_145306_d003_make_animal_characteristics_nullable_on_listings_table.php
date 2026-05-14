<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropForeign(['species_id']);
            $table->dropForeign(['size_id']);
            $table->dropForeign(['breed_id']);
            $table->dropForeign(['sex_id']);
        });

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->unsignedBigInteger('species_id')->nullable()->change();
            $table->unsignedBigInteger('size_id')->nullable()->change();
            $table->unsignedBigInteger('breed_id')->nullable()->change();
            $table->unsignedBigInteger('sex_id')->nullable()->change();
        });

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->foreign('species_id')->references('id')->on('species')->nullOnDelete();
            $table->foreign('size_id')->references('id')->on('sizes')->nullOnDelete();
            $table->foreign('breed_id')->references('id')->on('breeds')->nullOnDelete();
            $table->foreign('sex_id')->references('id')->on('sexes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            DB::statement('UPDATE animal_listings SET species_id = (SELECT id FROM species ORDER BY id LIMIT 1) WHERE species_id IS NULL');
            DB::statement('UPDATE animal_listings SET size_id = (SELECT id FROM sizes ORDER BY id LIMIT 1) WHERE size_id IS NULL');
            DB::statement('UPDATE animal_listings SET breed_id = (SELECT id FROM breeds ORDER BY id LIMIT 1) WHERE breed_id IS NULL');
            DB::statement('UPDATE animal_listings SET sex_id = (SELECT id FROM sexes ORDER BY id LIMIT 1) WHERE sex_id IS NULL');
        });

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropForeign(['species_id']);
            $table->dropForeign(['size_id']);
            $table->dropForeign(['breed_id']);
            $table->dropForeign(['sex_id']);
        });

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->unsignedBigInteger('species_id')->nullable(false)->change();
            $table->unsignedBigInteger('size_id')->nullable(false)->change();
            $table->unsignedBigInteger('breed_id')->nullable(false)->change();
            $table->unsignedBigInteger('sex_id')->nullable(false)->change();
        });

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->foreign('species_id')->references('id')->on('species')->cascadeOnDelete();
            $table->foreign('size_id')->references('id')->on('sizes')->cascadeOnDelete();
            $table->foreign('breed_id')->references('id')->on('breeds')->cascadeOnDelete();
            $table->foreign('sex_id')->references('id')->on('sexes')->cascadeOnDelete();
        });
    }
};
