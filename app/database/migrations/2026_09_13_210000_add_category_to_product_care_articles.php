<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_care_articles')) {
            return;
        }

        if (Schema::hasColumn('product_care_articles', 'product_id')) {
            try {
                Schema::table('product_care_articles', function (Blueprint $table): void {
                    $table->dropUnique(['product_id', 'slug']);
                });
            } catch (\Throwable) {
                // Індекс уже знятий або назва відрізняється (SQLite / повторний прогін).
            }

            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('product_care_articles', 'category_value_id')) {
            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->unsignedBigInteger('category_value_id')->nullable()->after('product_id');
                $table->index(['category_value_id', 'is_published']);
            });
        }

        $seen = [];
        foreach (DB::table('product_care_articles')->orderBy('id')->get(['id', 'slug']) as $row) {
            $slug = trim((string) $row->slug);
            if ($slug === '') {
                $slug = 'care-article-'.$row->id;
            }
            if (isset($seen[$slug])) {
                $slug = $slug.'-'.$row->id;
            }
            $seen[$slug] = true;
            if ($slug !== (string) $row->slug) {
                DB::table('product_care_articles')->where('id', $row->id)->update(['slug' => $slug]);
            }
        }

        try {
            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->unique('slug');
            });
        } catch (\Throwable) {
            // Унікальний індекс уже є.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_care_articles')) {
            return;
        }

        try {
            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->dropUnique(['slug']);
            });
        } catch (\Throwable) {
        }

        if (Schema::hasColumn('product_care_articles', 'category_value_id')) {
            Schema::table('product_care_articles', function (Blueprint $table): void {
                $table->dropIndex(['category_value_id', 'is_published']);
                $table->dropColumn('category_value_id');
            });
        }
    }
};
