<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Замовлення до появи inventory_committed отримали false, хоча вже тримають товар.
     * Без цього будь-яка зміна статусу списувала склад повторно (Order::updating → takeForOrder).
     * Скасовані та неуспішні/повернені лишаються false — у них товар не утримується.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'inventory_committed')) {
            return;
        }

        DB::table('orders')
            ->where('inventory_committed', false)
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->update(['inventory_committed' => true]);
    }

    public function down(): void
    {
        // Незворотно: після backfill не відрізнити старі замовлення від нових.
    }
};
