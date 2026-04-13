<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_targets')) {
            DB::table('promotion_targets')->where('target_type', 'accessory_variant')->delete();
        }

        Schema::dropIfExists('accessory_variants');
        Schema::dropIfExists('accessory_listings');
    }

    public function down(): void
    {
        // Відновлення таблиць — через повторний deploy старих міграцій; тут навмисно порожньо.
    }
};
