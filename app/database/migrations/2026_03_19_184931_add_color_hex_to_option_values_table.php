<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('option_values')) {
            return;
        }

        if (Schema::hasColumn('option_values', 'color_hex')) {
            return;
        }

        Schema::table('option_values', function (Blueprint $table) {
            $table->string('color_hex', 16)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('option_values') || ! Schema::hasColumn('option_values', 'color_hex')) {
            return;
        }

        Schema::table('option_values', function (Blueprint $table) {
            $table->dropColumn('color_hex');
        });
    }
};
