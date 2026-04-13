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

        if (! Schema::hasColumn('animal_listings', 'photos')) {
            Schema::table('animal_listings', function (Blueprint $table): void {
                $table->json('photos')->nullable()->after('search_tags');
            });
        }

        DB::table('animal_listings')
            ->orderBy('id')
            ->chunkById(100, function ($listings): void {
                foreach ($listings as $listing) {
                    $existingPhotos = json_decode((string) ($listing->photos ?? 'null'), true);
                    if (is_array($existingPhotos) && $existingPhotos !== []) {
                        continue;
                    }

                    $variantWithPhotos = DB::table('animal_variants')
                        ->where('animal_listing_id', $listing->id)
                        ->whereNotNull('photos')
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->first(['photos']);

                    if (! $variantWithPhotos) {
                        continue;
                    }

                    $photos = json_decode((string) $variantWithPhotos->photos, true);
                    if (! is_array($photos) || $photos === []) {
                        continue;
                    }

                    DB::table('animal_listings')
                        ->where('id', $listing->id)
                        ->update(['photos' => json_encode(array_values($photos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings') || ! Schema::hasColumn('animal_listings', 'photos')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropColumn('photos');
        });
    }
};
