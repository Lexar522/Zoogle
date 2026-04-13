<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->string('sku', 120)->nullable()->after('slug');
            $table->text('short_description')->nullable()->after('description');
            $table->json('photos')->nullable()->after('short_description');
            $table->json('variant_options')->nullable()->after('photos');
            $table->unsignedBigInteger('category_parent_value_id')->nullable()->after('variant_options');
            $table->unsignedBigInteger('category_value_id')->nullable()->after('category_parent_value_id');
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->foreign('category_parent_value_id')
                ->references('id')
                ->on('option_values')
                ->nullOnDelete();
            $table->foreign('category_value_id')
                ->references('id')
                ->on('option_values')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropForeign(['category_parent_value_id']);
            $table->dropForeign(['category_value_id']);
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn([
                'sku',
                'short_description',
                'photos',
                'variant_options',
                'category_parent_value_id',
                'category_value_id',
            ]);
        });
    }
};
