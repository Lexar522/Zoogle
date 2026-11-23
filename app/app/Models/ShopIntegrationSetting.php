<?php

namespace App\Models;

use App\Support\StorefrontMemo;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ShopIntegrationSetting extends Model
{
    protected $fillable = [
        'nova_poshta_api_key',
        'google_maps_api_key',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'pickup_enabled',
        'taxi_delivery_enabled',
        'contact_phone',
        'contact_email',
        'contact_instagram',
        'contact_viber',
        'contact_whatsapp',
        'contact_telegram',
        'online_payment_provider',
        'wayforpay_merchant_account',
        'wayforpay_secret_key',
        'wayforpay_merchant_domain',
    ];

    protected function casts(): array
    {
        return [
            'nova_poshta_api_key' => 'encrypted',
            'google_maps_api_key' => 'encrypted',
            'wayforpay_secret_key' => 'encrypted',
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'pickup_enabled' => 'boolean',
            'taxi_delivery_enabled' => 'boolean',
        ];
    }

    public function isPickupEnabled(): bool
    {
        return (bool) ($this->pickup_enabled ?? true);
    }

    public function isTaxiDeliveryEnabled(): bool
    {
        return (bool) ($this->taxi_delivery_enabled ?? true);
    }

    public static function record(): self
    {
        return static::query()->firstOrCreate([], []);
    }

    /** Лише для читання: контакти шапки, футер і сторінка товару беруть запис кілька разів за запит. */
    public static function storefront(): self
    {
        return StorefrontMemo::remember('shop-integration-setting', fn (): self => self::record());
    }

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontMemo::flush());
        static::deleted(fn () => StorefrontMemo::flush());
    }

    public function readEncrypted(string $attribute): ?string
    {
        $raw = $this->getRawOriginal($attribute);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            // Каст «encrypted» зберігає рядок без серіалізації — розшифровуємо так само
            $value = Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }

        $value = self::unwrapLegacySerialized($value);

        return trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Старі значення могли бути збережені через encrypt() — тоді всередині серіалізований рядок «s:N:"…";».
     */
    private static function unwrapLegacySerialized(string $value): string
    {
        if (preg_match('/^s:\d+:".*";$/s', $value) !== 1) {
            return $value;
        }

        $unserialized = @unserialize($value, ['allowed_classes' => false]);

        return is_string($unserialized) ? $unserialized : $value;
    }

    public function hasEncryptedRaw(string $attribute): bool
    {
        $raw = $this->getRawOriginal($attribute);

        return is_string($raw) && trim($raw) !== '';
    }

    public function isEncryptedReadable(string $attribute): bool
    {
        return $this->readEncrypted($attribute) !== null;
    }
}
