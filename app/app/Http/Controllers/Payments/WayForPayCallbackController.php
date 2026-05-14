<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\ApplyApprovedOrderPayment;
use App\Services\Payments\WayForPayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WayForPayCallbackController extends Controller
{
    public function __invoke(Request $request, WayForPayClient $wfp): JsonResponse|Response
    {
        if (! $wfp->isConfigured()) {
            abort(503, 'WayForPay not configured');
        }

        $data = WayForPayClient::parseCallbackPayload($request);
        if ($data === null) {
            $raw = $request->getContent();
            Log::warning('WayForPay callback: no usable payload', [
                'content_type' => $request->header('Content-Type'),
                'raw_prefix' => substr($raw, 0, 500),
            ]);

            return response('invalid payload', 400);
        }

        if (! $wfp->verifyServiceCallbackSignature($data)) {
            Log::warning('WayForPay callback: bad signature', [
                'orderReference' => $data['orderReference'] ?? null,
                'merchantAccount' => $data['merchantAccount'] ?? null,
                'configuredMerchantAccount' => $wfp->merchantAccount(),
                'transactionStatus' => $data['transactionStatus'] ?? null,
                'reasonCode' => $data['reasonCode'] ?? null,
                'amount' => $data['amount'] ?? null,
            ]);

            return response('bad signature', 400);
        }

        $orderReference = (string) ($data['orderReference'] ?? '');

        $parsed = WayForPayClient::parseOrderReference($orderReference);
        $orderId = $parsed['orderId'];
        $paymentLeg = $parsed['leg'];

        $order = Order::query()->find($orderId);
        if (! $order) {
            Log::warning('WayForPay callback: order not found', ['order_id' => $orderId]);

            $ref = $orderReference !== '' ? $orderReference : '0';

            return response()->json($wfp->serviceResponseAccept($ref));
        }

        $txStatus = trim((string) ($data['transactionStatus'] ?? ''));
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;

        if (strcasecmp($txStatus, 'Approved') !== 0) {
            $order->payment_last_callback_at = now();
            if (in_array(strtolower($txStatus), ['declined', 'expired', 'refunded', 'voided'], true)) {
                $order->payment_status = 'failed';
            }
            $order->save();

            return response()->json($wfp->serviceResponseAccept($orderReference));
        }

        app(ApplyApprovedOrderPayment::class)->apply($order, $paymentLeg, $amount);

        return response()->json($wfp->serviceResponseAccept($orderReference));
    }

}
