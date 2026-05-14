<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_values', function (Blueprint $table): void {
            $table->boolean('pickup_only_subtree')->default(false)->after('is_active');
            $table->boolean('defer_online_payment')->default(false)->after('pickup_only_subtree');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('deferred_online_payment')->default(false)->after('payment_status');
            $table->timestamp('online_payment_unlocked_at')->nullable()->after('deferred_online_payment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['deferred_online_payment', 'online_payment_unlocked_at']);
        });

        Schema::table('option_values', function (Blueprint $table): void {
            $table->dropColumn(['pickup_only_subtree', 'defer_online_payment']);
        });
    }
};
