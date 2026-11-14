<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderCustomerNotifier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Неоплачені онлайн-замовлення не мають тримати товар безстроково.
 * Замовлення з відкладеною оплатою чекають на менеджера, тому їх не чіпаємо.
 */
class CancelUnpaidOrders extends Command
{
    protected $signature = 'shop:cancel-unpaid-orders';

    protected $description = 'Скасовує нові онлайн-замовлення без оплати та повертає товар на склад';

    public function handle(OrderCustomerNotifier $notifier): int
    {
        $hours = max(1, (int) config('shop.unpaid_order_ttl_hours', 48));
        $cancelled = 0;

        Order::query()
            ->where('status', Order::STATUS_NEW)
            ->where('checkout_payment_method', 'online')
            ->whereIn('payment_status', ['pending', 'failed'])
            ->where('deferred_online_payment', false)
            ->where('mixed_payment_plan', false)
            ->whereNull('payment_issue')
            ->where('placed_at', '<', now()->subHours($hours))
            ->orderBy('id')
            ->each(function (Order $order) use ($notifier, &$cancelled): void {
                try {
                    $notifier->apply($order, Order::STATUS_CANCELLED, 'Замовлення скасовано автоматично, бо оплату не отримано.');
                    $cancelled++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Скасовано замовлень: {$cancelled}");

        return self::SUCCESS;
    }
}
