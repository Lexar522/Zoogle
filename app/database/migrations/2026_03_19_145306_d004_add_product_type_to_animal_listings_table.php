<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('animal_listings') || Schema::hasColumn('animal_listings', 'product_type')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->string('product_type')->default('animal')->after('slug');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('animal_listings') || ! Schema::hasColumn('animal_listings', 'product_type')) {
            return;
        }

        Schema::table('animal_listings', function (Blueprint $table): void {
            $table->dropIndex(['product_type']);
            $table->dropColumn('product_type');
        });
    }
};
