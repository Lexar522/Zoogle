<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('discount_mode', 32);
            $table->decimal('discount_value', 10, 2);
            $table->timestamps();

            $table->unique(['promotion_id', 'target_type', 'target_id'], 'promotion_targets_unique');
            $table->index(['target_type', 'target_id'], 'promotion_targets_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
