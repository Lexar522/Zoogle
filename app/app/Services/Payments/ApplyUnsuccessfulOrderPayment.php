<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Спільна логіка для неуспішних статусів платіжки (LiqPay / WayForPay).
 */
class ApplyUnsuccessfulOrderPayment
{
    private const ATTEMPT_FAILED = ['failure', 'error', 'declined', 'expired'];

    private const MONEY_RETURNED = ['reversed', 'refund', 'refunded', 'voided'];

    public function apply(Order $order, string $gatewayStatus): void
    {
        $status = strtolower(trim($gatewayStatus));
        $order->payment_last_callback_at = now();

        if (in_array($status, self::MONEY_RETURNED, true)) {
            $order->payment_status = 'failed';
        } elseif (in_array($status, self::ATTEMPT_FAILED, true)) {
            // Запізніла відмова за іншою спробою не скасовує вже отримані гроші.
            if ($this->hasReceivedPayment($order)) {
                Log::warning('Online payment callback: failed attempt ignored for paid order', [
                    'order_id' => $order->id,
                    'status' => $status,
                    'payment_status' => $order->payment_status,
                ]);
            } else {
                $order->payment_status = 'failed';
            }
        }

        $order->save();
    }

    private function hasReceivedPayment(Order $order): bool
    {
        return in_array((string) $order->payment_status, ['paid', 'partial'], true)
            || $order->paid_at !== null
            || $order->immediate_portion_paid_at !== null
            || $order->deferred_portion_paid_at !== null;
    }
}
