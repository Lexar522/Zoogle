<?php

namespace App\Console\Commands;

use App\Models\ShopIntegrationSetting;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class ReencryptIntegrationSettings extends Command
{
    private const ENCRYPTED_COLUMNS = ['nova_poshta_api_key', 'google_maps_api_key', 'wayforpay_secret_key'];

    protected $signature = 'shop:reencrypt-settings';

    protected $description = 'Перешифрувати ключі інтеграцій поточним APP_KEY (після заміни ключа; старий — у APP_PREVIOUS_KEYS)';

    public function handle(): int
    {
        $failed = 0;

        foreach (ShopIntegrationSetting::query()->get() as $setting) {
            foreach (self::ENCRYPTED_COLUMNS as $column) {
                $raw = $setting->getRawOriginal($column);
                if (! is_string($raw) || trim($raw) === '') {
                    continue;
                }

                try {
                    $plain = Crypt::decryptString($raw);
                } catch (DecryptException) {
                    $this->error("Не вдалося розшифрувати {$column}: перевірте APP_PREVIOUS_KEYS.");
                    $failed++;

                    continue;
                }

                ShopIntegrationSetting::query()
                    ->whereKey($setting->getKey())
                    ->toBase()
                    ->update([$column => Crypt::encryptString($plain)]);
                $this->info("OK: {$column}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
