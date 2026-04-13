<?php

use App\Models\OptionValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Доплати переносяться з option_values.price у variant_options[].value_prices на картці товару.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        DB::table('animal_listings')->orderBy('id')->chunkById(50, function ($rows): void {
            foreach ($rows as $row) {
                $raw = $row->variant_options ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $rowsData = is_string($raw) ? json_decode($raw, true) : $raw;
                if (! is_array($rowsData) || $rowsData === []) {
                    continue;
                }
                $changed = false;
                foreach ($rowsData as $i => $optRow) {
                    if (! is_array($optRow)) {
                        continue;
                    }
                    $existing = $optRow['value_prices'] ?? [];
                    if (is_array($existing) && $existing !== []) {
                        continue;
                    }
                    $ids = $optRow['option_value_ids'] ?? [];
                    if (! is_array($ids) || $ids === []) {
                        continue;
                    }
                    $map = [];
                    foreach ($ids as $id) {
                        $id = (int) $id;
                        if ($id <= 0) {
                            continue;
                        }
                        $p = OptionValue::query()->whereKey($id)->value('price');
                        if ($p !== null && (float) $p > 0) {
                            $map[(string) $id] = round((float) $p, 2);
                        }
                    }
                    if ($map !== []) {
                        $rowsData[$i]['value_prices'] = $map;
                        $changed = true;
                    }
                }
                if ($changed) {
                    DB::table('animal_listings')->where('id', $row->id)->update([
                        'variant_options' => json_encode($rowsData),
                    ]);
                }
            }
        });
    }
};
