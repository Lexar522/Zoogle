<?php

namespace App\Services;

use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderUpdate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Оновлення статусу / коментар магазину для покупця + лист як у звичайному інтернет-магазині.
 */
class OrderCustomerNotifier
{
    /**
     * Змінити статус (якщо передано) і за потреби надіслати лист.
     */
    public function apply(
        Order $order,
        ?string $newStatus,
        ?string $message,
        bool $notifyEmail = true,
        ?int $actorId = null,
    ): ?OrderUpdate {
        $previousStatus = (string) $order->status;
        $nextStatus = is_string($newStatus) && $newStatus !== '' ? $newStatus : $previousStatus;
        $statusChanged = $nextStatus !== $previousStatus;
        $text = trim((string) $message);

        if (! $statusChanged && $text === '') {
            return null;
        }

        if ($statusChanged) {
            $order->status = $nextStatus;
            $order->save();
        }

        return $this->record(
            $order->fresh() ?? $order,
            $statusChanged ? $previousStatus : null,
            $text !== '' ? $text : null,
            $notifyEmail,
            $actorId ?? Auth::id(),
        );
    }

    /**
     * Після збереження картки замовлення: статус уже записаний у БД.
     */
    public function notifyAfterSave(
        Order $order,
        ?string $previousStatus,
        ?string $message,
        bool $notifyEmail = true,
        ?int $actorId = null,
    ): ?OrderUpdate {
        $text = trim((string) $message);
        $current = (string) $order->status;
        $statusChanged = is_string($previousStatus) && $previousStatus !== '' && $previousStatus !== $current;

        if (! $statusChanged && $text === '') {
            return null;
        }

        return $this->record(
            $order,
            $statusChanged ? $previousStatus : null,
            $text !== '' ? $text : null,
            $notifyEmail,
            $actorId ?? Auth::id(),
        );
    }

    private function record(
        Order $order,
        ?string $previousStatus,
        ?string $message,
        bool $notifyEmail,
        ?int $actorId,
    ): OrderUpdate {
        $update = OrderUpdate::query()->create([
            'order_id' => $order->id,
            'status' => $order->status,
            'previous_status' => $previousStatus,
            'message' => $message,
            'notify_email' => $notifyEmail,
            'created_by' => $actorId,
        ]);

        if ($notifyEmail) {
            $this->sendEmail($order, $update);
        }

        return $update->fresh() ?? $update;
    }

    private function sendEmail(Order $order, OrderUpdate $update): void
    {
        $email = trim((string) $order->customer_email);
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new OrderStatusUpdated($order->fresh(['items']) ?? $order, $update));
            $update->forceFill(['email_sent_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('order_customer_email_failed', [
                'order_id' => $order->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
