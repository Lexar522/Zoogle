<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_header_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('featured_left_option_value_id')
                ->nullable()
                ->constrained('option_values')
                ->nullOnDelete();
            $table->foreignId('featured_right_option_value_id')
                ->nullable()
                ->constrained('option_values')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shop_header_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('image_path');
            $table->string('link_type', 32)->default('none');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('url', 2048)->nullable();
            $table->foreignId('category_option_value_id')->nullable()->constrained('option_values')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('alt_uk')->nullable();
            $table->string('alt_en')->nullable();
            $table->string('alt_ru')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_header_banners');
        Schema::dropIfExists('shop_header_settings');
    }
};
