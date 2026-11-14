<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Індекси під постійні фільтри вітрини: каталог завжди бере доступні товари свого типу,
 * видимі активні комплекти, а фільтр «Акції» шукає чинні акції за датами.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> */
    private array $indexes = [
        'products' => ['products_available_type_index' => ['is_available', 'product_type']],
        'bundles' => ['bundles_visible_active_index' => ['is_visible', 'is_active']],
        'promotions' => ['promotions_active_dates_index' => ['is_active', 'starts_at', 'ends_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if (! Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }
};
