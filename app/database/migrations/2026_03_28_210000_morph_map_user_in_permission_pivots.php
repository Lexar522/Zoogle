<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Після enforceMorphMap Spatie зберігає model_type як короткий ключ (user), а не FQCN.
     */
    public function up(): void
    {
        $from = 'App\\Models\\User';
        $to = 'user';

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('model_type', $from)->update(['model_type' => $to]);
        }
    }

    public function down(): void
    {
        $from = 'user';
        $to = 'App\\Models\\User';

        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('model_type', $from)->update(['model_type' => $to]);
        }
    }
};
