<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\ShopIntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Оплата через форму POST на secure.wayforpay.com (Purchase).
 *
 * @see https://wiki.wayforpay.com/en/view/852102
 */
class WayForPayClient
{
    public const PAY_URL = 'https://secure.wayforpay.com/pay';

    public const API_URL = 'https://api.wayforpay.com/api';

    /**
     * Розбір orderReference з callback (підтримка унікального суфікса ULID і старих форматів).
     *
     * @return array{orderId: int, leg: 'immediate'|'deferred'|null}
     */
    public static function parseOrderReference(string $orderReference): array
    {
        if (preg_match('/^(\d+)-(pay|imm|def)-([a-z0-9]{26})$/i', $orderReference, $m)) {
            return [
                'orderId' => (int) $m[1],
                'leg' => match ($m[2]) {
                    'imm' => 'immediate',
                    'def' => 'deferred',
                    default => null,
                },
            ];
        }

        if (preg_match('/^(\d+)-(imm|def)$/', $orderReference, $m)) {
            return [
                'orderId' => (int) $m[1],
                'leg' => $m[2] === 'imm' ? 'immediate' : 'deferred',
            ];
        }

        if (preg_match('/^(\d+)$/', $orderReference, $m)) {
            return [
                'orderId' => (int) $m[1],
                'leg' => null,
            ];
        }

        return [
            'orderId' => 0,
            'leg' => null,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->merchantAccount() !== '' && $this->secretKey() !== '' && $this->merchantDomainName() !== '';
    }

    /**
     * WayForPay може надсилати payload як JSON у body або як звичайні form поля.
     *
     * @return array<string, mixed>|null
     */
    public static function parseCallbackPayload(Request $request): ?array
    {
        $raw = $request->getContent();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && array_key_exists('merchantAccount', $decoded)) {
                return $decoded;
            }
        }

        $merged = $request->all();
        if (is_array($merged) && array_key_exists('merchantAccount', $merged)) {
            return $merged;
        }

        $reqOnly = $request->request->all();
        if (is_array($reqOnly) && array_key_exists('merchantAccount', $reqOnly)) {
            return $reqOnly;
        }

        return null;
    }

    public function merchantAccount(): string
    {
        $stored = ShopIntegrationSetting::query()->first()?->wayforpay_merchant_account;

        return trim(is_string($stored) ? $stored : '')
            ?: trim((string) Config::get('services.wayforpay.merchant_account'));
    }

    public function secretKey(): string
    {
        $stored = ShopIntegrationSetting::query()->first()?->wayforpay_secret_key;

        return trim(is_string($stored) ? $stored : '')
            ?: trim((string) Config::get('services.wayforpay.secret_key'));
    }

    public function merchantDomainName(): string
    {
        $stored = ShopIntegrationSetting::query()->first()?->wayforpay_merchant_domain;
        $fromDb = trim(is_string($stored) ? $stored : '');
        if ($fromDb !== '') {
            return self::normalizeDomain($fromDb);
        }

        $fromEnv = trim((string) Config::get('services.wayforpay.merchant_domain'));
        if ($fromEnv !== '') {
            return self::normalizeDomain($fromEnv);
        }

        $host = parse_url((string) Config::get('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? self::normalizeDomain($host) : '';
    }

    private static function normalizeDomain(string $host): string
    {
        $h = trim($host);
        $h = preg_replace('#^https?://#i', '', $h) ?? $h;
        $h = preg_replace('#/.*$#', '', $h) ?? $h;

        return strtolower($h);
    }

    /**
     * @param  'full'|'immediate'|'deferred'  $leg
     * @return array{action: string, fields: array<string, array<int, string>|string>}
     */
    public function purchaseFormFields(Order $order, string $leg = 'full'): array
    {
        $merchantAccount = $this->merchantAccount();
        $merchantDomainName = $this->merchantDomainName();
        $secretKey = $this->secretKey();

        $amount = match ($leg) {
            'immediate' => $order->effectiveImmediateSubtotal(),
            'deferred' => $order->effectiveDeferredSubtotal(),
            default => (float) $order->total,
        };

        // Унікальний orderReference на кожну спробу оплати — WayForPay повертає 1112 Duplicate Order ID,
        // якщо повторно надіслати той самий reference після завершеної/ініційованої транзакції.
        $nonce = Str::lower((string) Str::ulid());

        $orderReference = match ($leg) {
            'immediate' => $order->id.'-imm-'.$nonce,
            'deferred' => $order->id.'-def-'.$nonce,
            default => $order->id.'-pay-'.$nonce,
        };

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderDate = (string) (int) ($order->placed_at?->timestamp ?? time());

        $productName = match ($leg) {
            'immediate' => 'Замовлення '.$order->number.' — аксесуари / без відкладеної категорії',
            'deferred' => 'Замовлення '.$order->number.' — доплата (узгоджена онлайн-оплата)',
            default => 'Оплата замовлення '.$order->number,
        };

        $names = [$productName];
        $counts = ['1'];
        $prices = [$amountStr];

        $signString = implode(';', array_merge(
            [$merchantAccount, $merchantDomainName, $orderReference, $orderDate, $amountStr, 'UAH'],
            $names,
            $counts,
            $prices,
        ));

        $merchantSignature = hash_hmac('md5', $signString, $secretKey);

        $fields = [
            'merchantAccount' => $merchantAccount,
            'merchantAuthType' => 'SimpleSignature',
            'merchantDomainName' => $merchantDomainName,
            'merchantSignature' => $merchantSignature,
            'orderReference' => $orderReference,
            'orderDate' => $orderDate,
            'amount' => $amountStr,
            'currency' => 'UAH',
            'orderTimeout' => '49000',
            'productName' => $names,
            'productPrice' => $prices,
            'productCount' => $counts,
            'returnUrl' => route('checkout.success', [
                'order' => $order,
                'token' => $order->success_token,
                'wfp_ref' => $orderReference,
            ]),
            'serviceUrl' => route('payments.wayforpay.callback'),
            'clientEmail' => trim((string) $order->customer_email) ?: 'noreply@invalid.local',
            'clientPhone' => preg_replace('/\D+/', '', (string) $order->customer_phone) ?: '380000000000',
            'defaultPaymentSystem' => 'card',
            'language' => 'UA',
        ];

        return [
            'action' => self::PAY_URL,
            'fields' => $fields,
        ];
    }

    /**
     * Перевірка підпису callback на serviceUrl.
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyServiceCallbackSignature(array $data): bool
    {
        $secretKey = $this->secretKey();
        if ($secretKey === '') {
            return false;
        }

        $received = (string) ($data['merchantSignature'] ?? '');
        if ($received === '') {
            return false;
        }

        $merchantAccount = (string) ($data['merchantAccount'] ?? '');
        $orderReference = (string) ($data['orderReference'] ?? '');
        $currency = (string) ($data['currency'] ?? '');
        $authCode = (string) ($data['authCode'] ?? '');
        $cardPan = (string) ($data['cardPan'] ?? '');
        $transactionStatus = (string) ($data['transactionStatus'] ?? '');
        $reasonCode = (string) ($data['reasonCode'] ?? '');

        foreach (self::amountSignatureCandidates($data['amount'] ?? null) as $amount) {
            $signString = implode(';', [
                $merchantAccount,
                $orderReference,
                $amount,
                $currency,
                $authCode,
                $cardPan,
                $transactionStatus,
                $reasonCode,
            ]);

            $expected = hash_hmac('md5', $signString, $secretKey);
            if (hash_equals($expected, $received)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Перевірка статусу платежу напряму в WayForPay за orderReference.
     *
     * @return array<string, mixed>|null
     */
    public function checkStatus(string $orderReference): ?array
    {
        $merchantAccount = $this->merchantAccount();
        $secretKey = $this->secretKey();
        $ref = trim($orderReference);

        if ($merchantAccount === '' || $secretKey === '' || $ref === '') {
            return null;
        }

        $payload = [
            'transactionType' => 'CHECK_STATUS',
            'merchantAccount' => $merchantAccount,
            'orderReference' => $ref,
            'merchantSignature' => hash_hmac('md5', implode(';', [$merchantAccount, $ref]), $secretKey),
            'apiVersion' => 1,
        ];

        $response = Http::timeout(8)->acceptJson()->asJson()->post(self::API_URL, $payload);
        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Відповідь WayForPay після прийому callback (JSON).
     *
     * @return array{orderReference: string, status: string, time: int, signature: string}
     */
    public function serviceResponseAccept(string $orderReference): array
    {
        $time = time();
        $status = 'accept';
        $secretKey = $this->secretKey();
        $signature = hash_hmac('md5', implode(';', [$orderReference, $status, (string) $time]), $secretKey);

        return [
            'orderReference' => $orderReference,
            'status' => $status,
            'time' => $time,
            'signature' => $signature,
        ];
    }

    private static function formatAmountForSign(mixed $amount): string
    {
        $n = is_numeric($amount) ? (float) $amount : (float) preg_replace('/[^\d.]/', '', (string) $amount);

        return number_format($n, 2, '.', '');
    }

    /**
     * WayForPay у різних відповідях може підписувати суму як "1" або "1.00".
     *
     * @return list<string>
     */
    private static function amountSignatureCandidates(mixed $amount): array
    {
        $candidates = [self::formatAmountForSign($amount)];

        if (is_string($amount)) {
            $raw = trim(str_replace(',', '.', $amount));
            if ($raw !== '') {
                $candidates[] = $raw;
            }
        } elseif (is_int($amount) || is_float($amount)) {
            $candidates[] = rtrim(rtrim(sprintf('%.10F', (float) $amount), '0'), '.');
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $v): bool => $v !== '')));
    }
}
