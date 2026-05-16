<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;

class NovaPoshtaApiKey
{
    public function current(): string
    {
        $stored = ShopIntegrationSetting::record()->nova_poshta_api_key;
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        return trim((string) config('services.nova_poshta.api_key'));
    }

    public function isConfigured(): bool
    {
        return $this->current() !== '';
    }

    public function hasStoredKey(): bool
    {
        $stored = ShopIntegrationSetting::record()->nova_poshta_api_key;

        return is_string($stored) && trim($stored) !== '';
    }
}
