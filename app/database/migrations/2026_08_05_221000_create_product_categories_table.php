<?php

use App\Support\CatalogProductsTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $productsTable = CatalogProductsTable::name();

        if (! Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) use ($productsTable): void {
                $table->id();
                $table->foreignId('product_id')->constrained($productsTable)->cascadeOnDelete();
                $table->foreignId('category_value_id')->constrained('option_values')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['product_id', 'category_value_id'], 'product_categories_unique');
                $table->index(['category_value_id', 'product_id']);
                $table->index(['product_id', 'sort_order']);
            });
        }

        if (
            ! Schema::hasTable('product_categories')
            || ! Schema::hasTable($productsTable)
            || ! Schema::hasColumn($productsTable, 'category_parent_value_id')
        ) {
            return;
        }

        $hasLeaf = Schema::hasColumn($productsTable, 'category_value_id');
        $now = now();
        $columns = $hasLeaf
            ? ['id', 'category_parent_value_id', 'category_value_id']
            : ['id', 'category_parent_value_id'];

        DB::table($productsTable)
            ->select($columns)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($now, $hasLeaf): void {
                $insert = [];
                foreach ($rows as $row) {
                    $leaf = $hasLeaf ? (int) ($row->category_value_id ?? 0) : 0;
                    $root = (int) ($row->category_parent_value_id ?? 0);
                    $categoryId = $leaf > 0 ? $leaf : $root;
                    if ($categoryId <= 0) {
                        continue;
                    }

                    $insert[] = [
                        'product_id' => (int) $row->id,
                        'category_value_id' => $categoryId,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($insert !== []) {
                    DB::table('product_categories')->insertOrIgnore($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
