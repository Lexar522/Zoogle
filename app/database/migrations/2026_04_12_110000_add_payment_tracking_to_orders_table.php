<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_provider', 32)->nullable()->after('paid_at');
            $table->string('payment_external_id', 64)->nullable()->after('payment_provider');
            $table->text('payment_checkout_url')->nullable()->after('payment_external_id');
            $table->timestamp('payment_last_callback_at')->nullable()->after('payment_checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_provider',
                'payment_external_id',
                'payment_checkout_url',
                'payment_last_callback_at',
            ]);
        });
    }
};
