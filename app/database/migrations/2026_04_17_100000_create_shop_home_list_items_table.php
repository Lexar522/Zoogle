<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_home_list_items', function (Blueprint $table): void {
            $table->id();
            $table->string('list', 32);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['list', 'product_id']);
            $table->index(['list', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_home_list_items');
    }
};
