<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;

class NovaPoshtaApiKey
{
    public function current(): string
    {
        $stored = ShopIntegrationSetting::storefront()->readEncrypted('nova_poshta_api_key');
        if ($stored !== null) {
            return $stored;
        }

        return trim((string) config('services.nova_poshta.api_key'));
    }

    public function isConfigured(): bool
    {
        return $this->current() !== '';
    }

    public function hasStoredKey(): bool
    {
        return ShopIntegrationSetting::storefront()->hasEncryptedRaw('nova_poshta_api_key');
    }

    public function isStoredKeyReadable(): bool
    {
        return ShopIntegrationSetting::storefront()->isEncryptedReadable('nova_poshta_api_key');
    }
}
