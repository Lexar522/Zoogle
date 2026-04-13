<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        $drops = array_values(array_filter([
            Schema::hasColumn('animal_listings', 'price') ? 'price' : null,
            Schema::hasColumn('animal_listings', 'is_available') ? 'is_available' : null,
            Schema::hasColumn('animal_listings', 'is_visible') ? 'is_visible' : null,
            Schema::hasColumn('animal_listings', 'allows_preorder') ? 'allows_preorder' : null,
            Schema::hasColumn('animal_listings', 'is_sold') ? 'is_sold' : null,
        ]));

        if ($drops === []) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table) use ($drops): void {
            $table->dropColumn($drops);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            if (! Schema::hasColumn('animal_listings', 'price')) {
                $table->decimal('price', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('animal_listings', 'is_available')) {
                $table->boolean('is_available')->default(true);
            }
            if (! Schema::hasColumn('animal_listings', 'is_visible')) {
                $table->boolean('is_visible')->default(true);
            }
            if (! Schema::hasColumn('animal_listings', 'allows_preorder')) {
                $table->boolean('allows_preorder')->default(false);
            }
            if (! Schema::hasColumn('animal_listings', 'is_sold')) {
                $table->boolean('is_sold')->default(false);
            }
        });
    }
};
