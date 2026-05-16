<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;

class GoogleMapsApiKey
{
    public function current(): string
    {
        $stored = ShopIntegrationSetting::record()->google_maps_api_key;
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        return trim((string) config('services.google.maps_api_key'));
    }

    public function isConfigured(): bool
    {
        return $this->current() !== '';
    }

    public function hasStoredKey(): bool
    {
        $stored = ShopIntegrationSetting::record()->google_maps_api_key;

        return is_string($stored) && trim($stored) !== '';
    }
}
