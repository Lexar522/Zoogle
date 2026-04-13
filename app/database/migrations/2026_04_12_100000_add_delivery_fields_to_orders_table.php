<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_type', 32)->default('pickup')->after('customer_address');
            $table->string('delivery_city', 120)->nullable()->after('delivery_type');
            $table->string('delivery_branch', 255)->nullable()->after('delivery_city');
            $table->text('delivery_address')->nullable()->after('delivery_branch');
            $table->text('customer_notes')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_type',
                'delivery_city',
                'delivery_branch',
                'delivery_address',
                'customer_notes',
            ]);
        });
    }
};
