<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('discount_rules');
    }

    public function down(): void
    {
        // Відновлення не потрібне: знижки на комплекти ведуться через promotion_targets (target_type = bundle).
    }
};
