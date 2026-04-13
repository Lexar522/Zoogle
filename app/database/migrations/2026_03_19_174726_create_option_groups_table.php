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
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->string('product_type'); // animal|accessory
            $table->string('name');
            $table->string('slug');
            $table->string('selection_mode')->default('single');
            $table->string('value_type')->default('text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_type', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_groups');
    }
};
