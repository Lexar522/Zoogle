<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'nova_poshta_ttn')) {
                $table->string('nova_poshta_ttn', 64)->nullable()->after('delivery_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'nova_poshta_ttn')) {
                $table->dropColumn('nova_poshta_ttn');
            }
        });
    }
};
