<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Акції для тварин переводимо з прив’язки до animal_variants на animal_listings.
     * Записи без відповідного варіанта видаляються.
     */
    public function up(): void
    {
        if (! Schema::hasTable('promotion_targets')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql' && Schema::hasTable('animal_variants')) {
            DB::statement("
                UPDATE promotion_targets pt
                INNER JOIN animal_variants av ON av.id = pt.target_id
                SET pt.target_id = av.animal_listing_id, pt.target_type = 'animal_listing'
                WHERE pt.target_type = 'animal_variant'
            ");
        } elseif (Schema::hasTable('animal_variants')) {
            $rows = DB::table('promotion_targets')
                ->where('target_type', 'animal_variant')
                ->orderBy('id')
                ->get(['id', 'target_id']);

            foreach ($rows as $row) {
                $lid = DB::table('animal_variants')
                    ->where('id', (int) $row->target_id)
                    ->value('animal_listing_id');

                if ($lid) {
                    DB::table('promotion_targets')
                        ->where('id', $row->id)
                        ->update([
                            'target_type' => 'animal_listing',
                            'target_id' => (int) $lid,
                        ]);
                }
            }
        }

        DB::table('promotion_targets')
            ->where('target_type', 'animal_variant')
            ->delete();
    }

    public function down(): void
    {
        // Неможливо відновити прив’язку до конкретного варіанта без додаткових даних.
    }
};
