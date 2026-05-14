<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;
use App\Services\Payments\LiqPayClient;
use App\Services\Payments\WayForPayClient;

/**
 * Активний провайдер онлайн-оплати з налаштувань інтеграцій (або LiqPay за замовчуванням).
 */
final class OnlinePaymentSettings
{
    public const PROVIDER_LIQPAY = 'liqpay';

    public const PROVIDER_WAYFORPAY = 'wayforpay';

    public function provider(): string
    {
        $p = ShopIntegrationSetting::query()->first()?->online_payment_provider;

        return $p === self::PROVIDER_WAYFORPAY ? self::PROVIDER_WAYFORPAY : self::PROVIDER_LIQPAY;
    }

    public function isConfigured(): bool
    {
        return match ($this->provider()) {
            self::PROVIDER_WAYFORPAY => app(WayForPayClient::class)->isConfigured(),
            default => app(LiqPayClient::class)->isConfigured(),
        };
    }

    public function providerLabel(): string
    {
        return match ($this->provider()) {
            self::PROVIDER_WAYFORPAY => 'WayForPay',
            default => 'LiqPay',
        };
    }

    public function hasWayForPayCredentialsInDatabase(): bool
    {
        $r = ShopIntegrationSetting::query()->first();

        return is_string($r?->wayforpay_merchant_account) && trim($r->wayforpay_merchant_account) !== ''
            && is_string($r?->wayforpay_secret_key) && trim($r->wayforpay_secret_key) !== '';
    }

    public function hasLiqPayInEnv(): bool
    {
        return trim((string) config('services.liqpay.public_key')) !== ''
            && trim((string) config('services.liqpay.private_key')) !== '';
    }
}
