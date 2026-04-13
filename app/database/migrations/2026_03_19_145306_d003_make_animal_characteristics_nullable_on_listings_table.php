<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        Schema::table('animal_listings', function (): void {
            // Drop FK constraints before changing nullability.
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_species_id_foreign');
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_size_id_foreign');
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_breed_id_foreign');
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_sex_id_foreign');

            DB::statement('ALTER TABLE animal_listings MODIFY species_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE animal_listings MODIFY size_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE animal_listings MODIFY breed_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE animal_listings MODIFY sex_id BIGINT UNSIGNED NULL');

            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_species_id_foreign FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_size_id_foreign FOREIGN KEY (size_id) REFERENCES sizes(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_breed_id_foreign FOREIGN KEY (breed_id) REFERENCES breeds(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_sex_id_foreign FOREIGN KEY (sex_id) REFERENCES sexes(id) ON DELETE SET NULL');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        Schema::table('animal_listings', function (): void {
            DB::statement('UPDATE animal_listings SET species_id = (SELECT id FROM species ORDER BY id LIMIT 1) WHERE species_id IS NULL');
            DB::statement('UPDATE animal_listings SET size_id = (SELECT id FROM sizes ORDER BY id LIMIT 1) WHERE size_id IS NULL');
            DB::statement('UPDATE animal_listings SET breed_id = (SELECT id FROM breeds ORDER BY id LIMIT 1) WHERE breed_id IS NULL');
            DB::statement('UPDATE animal_listings SET sex_id = (SELECT id FROM sexes ORDER BY id LIMIT 1) WHERE sex_id IS NULL');

            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_species_id_foreign');
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_size_id_foreign');
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_breed_id_foreign');
            DB::statement('ALTER TABLE animal_listings DROP FOREIGN KEY animal_listings_sex_id_foreign');

            DB::statement('ALTER TABLE animal_listings MODIFY species_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE animal_listings MODIFY size_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE animal_listings MODIFY breed_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE animal_listings MODIFY sex_id BIGINT UNSIGNED NOT NULL');

            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_species_id_foreign FOREIGN KEY (species_id) REFERENCES species(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_size_id_foreign FOREIGN KEY (size_id) REFERENCES sizes(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_breed_id_foreign FOREIGN KEY (breed_id) REFERENCES breeds(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE animal_listings ADD CONSTRAINT animal_listings_sex_id_foreign FOREIGN KEY (sex_id) REFERENCES sexes(id) ON DELETE CASCADE');
        });
    }
};
