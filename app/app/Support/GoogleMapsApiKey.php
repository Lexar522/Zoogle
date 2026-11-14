<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;

class GoogleMapsApiKey
{
    public function current(): string
    {
        $stored = ShopIntegrationSetting::storefront()->readEncrypted('google_maps_api_key');
        if ($stored !== null) {
            return $stored;
        }

        return trim((string) config('services.google.maps_api_key'));
    }

    public function isConfigured(): bool
    {
        return $this->current() !== '';
    }

    public function hasStoredKey(): bool
    {
        return ShopIntegrationSetting::storefront()->hasEncryptedRaw('google_maps_api_key');
    }

    public function isStoredKeyReadable(): bool
    {
        return ShopIntegrationSetting::storefront()->isEncryptedReadable('google_maps_api_key');
    }
}
