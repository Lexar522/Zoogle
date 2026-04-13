<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Видаляє колонки вид/розмір/порода/стать та довідники після переходу на опції.
     * Дата після create_animal_listings та add_available_sexes, щоб працював порядок на fresh migrate.
     */
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        $hasSpecies = Schema::hasColumn('animal_listings', 'species_id');
        $hasSize = Schema::hasColumn('animal_listings', 'size_id');
        $hasBreed = Schema::hasColumn('animal_listings', 'breed_id');
        $hasSex = Schema::hasColumn('animal_listings', 'sex_id');

        if ($hasSpecies || $hasSize || $hasBreed || $hasSex) {
            Schema::table('animal_listings', function (Blueprint $table) use ($hasSpecies, $hasSize, $hasBreed, $hasSex): void {
                if ($hasSpecies) {
                    $table->dropForeign(['species_id']);
                }
                if ($hasSize) {
                    $table->dropForeign(['size_id']);
                }
                if ($hasBreed) {
                    $table->dropForeign(['breed_id']);
                }
                if ($hasSex) {
                    $table->dropForeign(['sex_id']);
                }
            });
        }

        try {
            Schema::table('animal_listings', function (Blueprint $table): void {
                $table->dropIndex('listing_cascade_idx');
            });
        } catch (Throwable) {
            // індекс уже відсутній або інша СУБД
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('animal_listings', 'species_id') ? 'species_id' : null,
            Schema::hasColumn('animal_listings', 'size_id') ? 'size_id' : null,
            Schema::hasColumn('animal_listings', 'breed_id') ? 'breed_id' : null,
            Schema::hasColumn('animal_listings', 'sex_id') ? 'sex_id' : null,
            Schema::hasColumn('animal_listings', 'available_sexes') ? 'available_sexes' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('animal_listings', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        Schema::dropIfExists('breeds');
        Schema::dropIfExists('species');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('sexes');
    }

    public function down(): void
    {
        // Повернення старих довідників не відновлюємо — дані втрачені після up().
    }
};
