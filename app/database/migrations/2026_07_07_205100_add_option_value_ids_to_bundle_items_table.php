<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_items', function (Blueprint $table): void {
            $table->json('option_value_ids')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('bundle_items', function (Blueprint $table): void {
            $table->dropColumn('option_value_ids');
        });
    }
};
