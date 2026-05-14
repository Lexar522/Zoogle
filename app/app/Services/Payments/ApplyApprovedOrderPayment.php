<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Спільна логіка після успішної онлайн-оплати (LiqPay / WayForPay).
 */
class ApplyApprovedOrderPayment
{
    public function apply(Order $order, ?string $paymentLeg, ?float $amount): void
    {
        DB::transaction(function () use ($order, $paymentLeg, $amount): void {
            $order->refresh();
            $order->payment_last_callback_at = now();

            $expected = match ($paymentLeg) {
                'immediate' => $order->effectiveImmediateSubtotal(),
                'deferred' => $order->effectiveDeferredSubtotal(),
                default => (float) $order->total,
            };

            if ($amount !== null && ! self::amountMatchesExpected((float) $amount, $expected, (float) $order->total)) {
                Log::warning('Online payment callback: amount mismatch', [
                    'order_id' => $order->id,
                    'leg' => $paymentLeg,
                    'expected' => $expected,
                    'got' => $amount,
                    'order_total' => $order->total,
                ]);
                $order->save();

                return;
            }

            if ($paymentLeg === 'immediate') {
                if ($order->immediate_portion_paid_at === null) {
                    $order->immediate_portion_paid_at = now();
                }
                $stillDeferred = $order->effectiveDeferredSubtotal() > 0.00001;
                if ($order->mixed_payment_plan && $stillDeferred) {
                    $order->payment_status = 'partial';
                } else {
                    $order->payment_status = 'paid';
                    $order->paid_at = now();
                    if ($order->status === 'new') {
                        $order->status = Order::STATUS_PAID;
                    }
                }
            } elseif ($paymentLeg === 'deferred') {
                if ($order->deferred_portion_paid_at === null) {
                    $order->deferred_portion_paid_at = now();
                }
                $order->payment_status = 'paid';
                $order->paid_at = now();
                if ($order->status === 'new') {
                    $order->status = Order::STATUS_PAID;
                }
            } else {
                if ($order->payment_status !== 'paid') {
                    $order->payment_status = 'paid';
                    $order->paid_at = now();
                }
                if ($order->status === 'new') {
                    $order->status = Order::STATUS_PAID;
                }
            }

            $order->save();
        });
    }

    /**
     * Допуск на округлення WayForPay / банку та різницю «очікувана частина» vs фактично списана сума.
     */
    private static function amountMatchesExpected(float $paid, float $expected, float $orderTotal): bool
    {
        if ($expected <= 0.00001) {
            return abs($paid - $orderTotal) <= max(1.0, $orderTotal * 0.01);
        }

        $tol = max(1.0, round($expected * 0.01, 2));

        if (abs($paid - $expected) <= $tol) {
            return true;
        }

        // Частина платежів приходить із сумою рівною повному total замовлення
        if ($orderTotal > 0.00001) {
            $tolT = max(1.0, round($orderTotal * 0.01, 2));

            return abs($paid - $orderTotal) <= $tolT;
        }

        return false;
    }
}
