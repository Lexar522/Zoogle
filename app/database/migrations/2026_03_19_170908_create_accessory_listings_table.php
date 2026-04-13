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
        Schema::create('accessory_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->boolean('allows_preorder')->default(false);
            $table->json('search_tags')->nullable();
            $table->json('photos')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_visible', 'is_available'], 'accessory_visibility_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessory_listings');
    }
};
