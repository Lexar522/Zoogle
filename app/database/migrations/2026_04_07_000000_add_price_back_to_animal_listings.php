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

        if (! Schema::hasColumn('animal_listings', 'price')) {
            Schema::table('animal_listings', function (Blueprint $table): void {
                $table->decimal('price', 10, 2)->default(0)->after('dimensions');
            });
        }

        DB::table('animal_listings')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $minPrice = DB::table('animal_variants')
                        ->where('animal_listing_id', $row->id)
                        ->min('price');

                    if ($minPrice === null) {
                        continue;
                    }

                    DB::table('animal_listings')
                        ->where('id', $row->id)
                        ->update(['price' => round((float) $minPrice, 2)]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings') || ! Schema::hasColumn('animal_listings', 'price')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
