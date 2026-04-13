<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('animal_variants', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('photos');
        });

        Schema::table('accessory_variants', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('photos');
        });

        $animalIds = DB::table('animal_variants')->orderBy('id')->pluck('id');
        foreach ($animalIds as $index => $id) {
            DB::table('animal_variants')->where('id', $id)->update(['sort_order' => $index + 1]);
        }

        $accessoryIds = DB::table('accessory_variants')->orderBy('id')->pluck('id');
        foreach ($accessoryIds as $index => $id) {
            DB::table('accessory_variants')->where('id', $id)->update(['sort_order' => $index + 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animal_variants', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });

        Schema::table('accessory_variants', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });
    }
};
