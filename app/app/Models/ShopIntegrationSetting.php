<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopIntegrationSetting extends Model
{
    protected $fillable = [
        'nova_poshta_api_key',
        'google_maps_api_key',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
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
        ];
    }

    public static function record(): self
    {
        return static::query()->firstOrCreate([], []);
    }
}
