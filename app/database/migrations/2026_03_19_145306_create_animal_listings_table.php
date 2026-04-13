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
        Schema::create('animal_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('dimensions')->nullable();
            $table->foreignId('species_id')->constrained()->cascadeOnDelete();
            $table->foreignId('size_id')->constrained()->cascadeOnDelete();
            $table->foreignId('breed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sex_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->boolean('allows_preorder')->default(false);
            $table->boolean('is_sold')->default(false);
            $table->json('search_tags')->nullable();
            $table->json('photos')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['species_id', 'size_id', 'breed_id', 'sex_id'], 'listing_cascade_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('animal_listings');
    }
};
