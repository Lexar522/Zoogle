<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\ApplyApprovedOrderPayment;
use App\Services\Payments\LiqPayClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $rawOrderId = (string) ($payload['order_id'] ?? '');
        $paymentLeg = null;
        if (preg_match('/^(\d+)-(imm|def)$/', $rawOrderId, $m)) {
            $orderId = (int) $m[1];
            $paymentLeg = $m[2] === 'imm' ? 'immediate' : 'deferred';
        } else {
            $orderId = (int) $rawOrderId;
        }

        $order = Order::query()->find($orderId);
        if (! $order) {
            Log::warning('LiqPay callback: order not found', ['order_id' => $orderId]);

            return response('OK', 200);
        }

        $status = (string) ($payload['status'] ?? '');
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;

        $order->payment_last_callback_at = now();
        if (! empty($payload['payment_id'])) {
            $order->payment_external_id = (string) $payload['payment_id'];
        }
        $order->save();

        $paidStatuses = ['success', 'sandbox'];
        if (! in_array($status, $paidStatuses, true)) {
            return response('OK', 200);
        }

        app(ApplyApprovedOrderPayment::class)->apply($order->fresh(), $paymentLeg, $amount);

        return response('OK', 200);
    }
}
