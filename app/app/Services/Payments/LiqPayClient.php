<?php

namespace App\Services\Payments;

use App\Models\Order;

class LiqPayClient
{
    public function isConfigured(): bool
    {
        $public = config('services.liqpay.public_key');
        $private = config('services.liqpay.private_key');

        return is_string($public) && $public !== '' && is_string($private) && $private !== '';
    }

    /**
     * @return array{data: string, signature: string}
     */
    public function checkoutFormData(Order $order): array
    {
        $privateKey = (string) config('services.liqpay.private_key');
        $params = [
            'version' => 3,
            'public_key' => (string) config('services.liqpay.public_key'),
            'action' => 'pay',
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => 'UAH',
            'description' => 'Оплата замовлення '.$order->number,
            'order_id' => (string) $order->id,
            'result_url' => route('checkout.success', ['order' => $order, 'token' => $order->success_token]),
            'server_url' => route('payments.liqpay.callback'),
            'sandbox' => config('services.liqpay.sandbox') ? 1 : 0,
        ];

        $data = base64_encode(json_encode($params, JSON_THROW_ON_ERROR));
        $signature = base64_encode(sha1($privateKey.$data.$privateKey, true));

        return compact('data', 'signature');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseCallback(string $data, string $signature): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $privateKey = (string) config('services.liqpay.private_key');
        $expected = base64_encode(sha1($privateKey.$data.$privateKey, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $json = base64_decode($data, true);
        if ($json === false) {
            return null;
        }

        $parsed = json_decode($json, true);

        return is_array($parsed) ? $parsed : null;
    }
}
