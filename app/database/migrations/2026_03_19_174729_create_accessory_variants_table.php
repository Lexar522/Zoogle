<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accessory_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_listing_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->boolean('allows_preorder')->default(false);
            $table->json('options')->nullable(); // [{option_group_id: X, option_value_id: Y}, ...]
            $table->json('photos')->nullable();
            $table->timestamps();

            $table->index(['accessory_listing_id', 'is_visible', 'is_available'], 'accessory_variant_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessory_variants');
    }
};
