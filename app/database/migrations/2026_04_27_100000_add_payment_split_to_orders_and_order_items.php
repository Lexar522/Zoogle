<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('immediate_subtotal', 10, 2)->nullable()->after('total');
            $table->decimal('deferred_subtotal', 10, 2)->nullable()->after('immediate_subtotal');
            $table->boolean('mixed_payment_plan')->default(false)->after('deferred_subtotal');
            $table->string('checkout_payment_method', 16)->nullable()->after('mixed_payment_plan');
            $table->timestamp('immediate_portion_paid_at')->nullable()->after('paid_at');
            $table->timestamp('deferred_portion_paid_at')->nullable()->after('immediate_portion_paid_at');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'line_defers_online_payment')) {
                $table->boolean('line_defers_online_payment')->default(false)->after('line_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'line_defers_online_payment')) {
                $table->dropColumn('line_defers_online_payment');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'immediate_subtotal',
                'deferred_subtotal',
                'mixed_payment_plan',
                'checkout_payment_method',
                'immediate_portion_paid_at',
                'deferred_portion_paid_at',
            ]);
        });
    }
};
