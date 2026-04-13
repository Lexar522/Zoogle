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
        if (! Schema::hasTable('option_groups')) {
            return;
        }

        Schema::table('option_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('option_groups', 'selection_mode')) {
                $table->string('selection_mode')->default('single')->after('slug');
            }
            if (! Schema::hasColumn('option_groups', 'value_type')) {
                $table->string('value_type')->default('text')->after('selection_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('option_groups')) {
            return;
        }

        Schema::table('option_groups', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('option_groups', 'selection_mode')) {
                $cols[] = 'selection_mode';
            }
            if (Schema::hasColumn('option_groups', 'value_type')) {
                $cols[] = 'value_type';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
