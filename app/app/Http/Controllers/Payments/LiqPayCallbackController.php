<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\LiqPayClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiqPayCallbackController extends Controller
{
    public function __invoke(Request $request, LiqPayClient $liqPay): Response
    {
        $data = (string) $request->input('data', '');
        $signature = (string) $request->input('signature', '');
        $payload = $liqPay->parseCallback($data, $signature);
        if ($payload === null) {
            abort(400, 'Invalid signature');
        }

        $orderId = (int) ($payload['order_id'] ?? 0);
        $order = Order::query()->find($orderId);
        if (! $order) {
            Log::warning('LiqPay callback: order not found', ['order_id' => $orderId]);

            return response('OK', 200);
        }

        $status = (string) ($payload['status'] ?? '');
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;

        DB::transaction(function () use ($order, $payload, $status, $amount): void {
            $order->refresh();
            $order->payment_last_callback_at = now();
            if (! empty($payload['payment_id'])) {
                $order->payment_external_id = (string) $payload['payment_id'];
            }

            $paidStatuses = ['success', 'sandbox'];
            if (in_array($status, $paidStatuses, true)) {
                if ($amount !== null && abs($amount - (float) $order->total) > 0.02) {
                    Log::warning('LiqPay callback: amount mismatch', [
                        'order_id' => $order->id,
                        'expected' => $order->total,
                        'got' => $amount,
                    ]);
                } else {
                    if ($order->payment_status !== 'paid') {
                        $order->payment_status = 'paid';
                        $order->paid_at = now();
                    }
                    if ($order->status === 'new') {
                        $order->status = Order::STATUS_PAID;
                    }
                }
            }

            $order->save();
        });

        return response('OK', 200);
    }
}
