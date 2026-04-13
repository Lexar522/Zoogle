<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->foreignId('category_parent_value_id')
                ->nullable()
                ->after('product_type')
                ->constrained('option_values')
                ->nullOnDelete();
            $table->foreignId('category_value_id')
                ->nullable()
                ->after('category_parent_value_id')
                ->constrained('option_values')
                ->nullOnDelete();
        });

        $categoryGroupId = (int) (DB::table('option_groups')
            ->where('slug', 'category')
            ->value('id') ?? 0);

        if ($categoryGroupId <= 0) {
            return;
        }

        DB::table('animal_listings')
            ->select(['id', 'variant_options'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($categoryGroupId): void {
                foreach ($rows as $row) {
                    $variantOptions = json_decode((string) ($row->variant_options ?? '[]'), true);
                    if (! is_array($variantOptions)) {
                        continue;
                    }

                    $pickedCategoryId = 0;
                    foreach ($variantOptions as $optionRow) {
                        if ((int) ($optionRow['option_group_id'] ?? 0) !== $categoryGroupId) {
                            continue;
                        }

                        $pickedCategoryId = (int) collect($optionRow['option_value_ids'] ?? [])
                            ->map(fn ($id): int => (int) $id)
                            ->first();
                        break;
                    }

                    if ($pickedCategoryId <= 0) {
                        continue;
                    }

                    $selected = DB::table('option_values')
                        ->where('option_group_id', $categoryGroupId)
                        ->where('id', $pickedCategoryId)
                        ->first(['id', 'parent_id']);

                    if (! $selected) {
                        continue;
                    }

                    DB::table('animal_listings')
                        ->where('id', (int) $row->id)
                        ->update([
                            'category_parent_value_id' => (int) ($selected->parent_id ?: $selected->id),
                            'category_value_id' => $selected->parent_id ? (int) $selected->id : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_value_id');
            $table->dropConstrainedForeignId('category_parent_value_id');
        });
    }
};
