<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Спільна логіка після успішної онлайн-оплати (LiqPay / WayForPay).
 */
class ApplyApprovedOrderPayment
{
    private const CURRENCY = 'UAH';

    private const AMOUNT_TOLERANCE = 1.0;

    public function apply(Order $order, ?string $paymentLeg, ?float $amount, ?string $currency = null): void
    {
        DB::transaction(function () use ($order, $paymentLeg, $amount, $currency): void {
            $order->refresh();
            $order->payment_last_callback_at = now();

            if (filled($currency) && strtoupper(trim((string) $currency)) !== self::CURRENCY) {
                $order->flagPaymentIssue('Платіж надійшов у валюті '.strtoupper(trim((string) $currency)).' на суму '.($amount ?? '—').'. Оплату не зараховано: перевірте її в кабінеті платіжної системи.');
                $order->save();

                return;
            }

            $expected = match ($paymentLeg) {
                'immediate' => $order->effectiveImmediateSubtotal(),
                'deferred' => $order->effectiveDeferredSubtotal(),
                default => (float) $order->total,
            };

            if ($amount === null || ! self::amountMatchesExpected($amount, $expected, (float) $order->total)) {
                Log::warning('Online payment callback: amount mismatch', [
                    'order_id' => $order->id,
                    'leg' => $paymentLeg,
                    'expected' => $expected,
                    'got' => $amount,
                    'order_total' => $order->total,
                ]);
                $order->flagPaymentIssue('Сума платежу ('.($amount ?? 'не вказана').' грн) не збігається з очікуваною ('.$expected.' грн). Оплату не зараховано: перевірте її в кабінеті платіжної системи.');
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
                    $order->paid_at ??= now();
                    if ($order->status === 'new') {
                        $order->status = Order::STATUS_PAID;
                    }
                }
            } elseif ($paymentLeg === 'deferred') {
                if ($order->deferred_portion_paid_at === null) {
                    $order->deferred_portion_paid_at = now();
                }
                $order->payment_status = 'paid';
                $order->paid_at ??= now();
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

            if ($order->status === Order::STATUS_CANCELLED) {
                $order->flagPaymentIssue('Оплата надійшла за скасоване замовлення. Поверніть гроші покупцю або відновіть замовлення.');
                $order->save();

                return;
            }

            // Гроші вже отримано: нестача товару не має скасувати запис про оплату.
            try {
                DB::transaction(fn () => $order->save());
            } catch (ValidationException) {
                $order->flagPaymentIssue('Оплату отримано, але товару вже немає на складі. Зв\'яжіться з покупцем: замініть товар або поверніть гроші.');
                $order->saveQuietly();
            }
        });
    }

    /**
     * Допуск 1 грн на округлення та різницю «очікувана частина» vs фактично списана сума.
     */
    private static function amountMatchesExpected(float $paid, float $expected, float $orderTotal): bool
    {
        if ($expected <= 0.00001) {
            return abs($paid - $orderTotal) <= self::AMOUNT_TOLERANCE;
        }

        if (abs($paid - $expected) <= self::AMOUNT_TOLERANCE) {
            return true;
        }

        // Частина платежів приходить із сумою рівною повному total замовлення
        if ($orderTotal > 0.00001) {
            return abs($paid - $orderTotal) <= self::AMOUNT_TOLERANCE;
        }

        return false;
    }
}
