<?php

namespace App\Console\Commands;

use App\Services\NovaPoshta\NovaPoshtaClient;
use App\Support\NovaPoshtaApiKey;
use Illuminate\Console\Command;

class NovaPoshtaVerifyCommand extends Command
{
    protected $signature = 'nova-poshta:verify';

    protected $description = 'Перевірити NOVA_POSHTA_API_KEY і показати приклад міста та кількість відділень';

    public function handle(NovaPoshtaClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('Ключ API не задано ні в адмінці (Інтеграції), ні в .env (NOVA_POSHTA_API_KEY).');
            $this->line('');
            $this->line('Варіанти:');
            $this->line('  • Адмінка → Налаштування → Інтеграції → зберегти ключ');
            $this->line('  • Або в .env: NOVA_POSHTA_API_KEY=… потім php artisan config:clear');
            $this->line('');
            $this->line('Ключ у кабінеті НП: https://my.novaposhta.ua/ → Налаштування → Безпека → API 2.0');

            return self::FAILURE;
        }

        $this->line('Джерело ключа: '.(app(NovaPoshtaApiKey::class)->hasStoredKey() ? 'база даних (адмінка)' : '.env'));
        $this->line('');

        $cities = $client->searchCities('Київ', 0);
        if ($cities === []) {
            $probe = $client->call('Address', 'getCities', [
                'FindByString' => 'Київ',
                'Page' => '0',
            ]);
            $this->error('API Нової Пошти не повернув міста (getCities).');
            foreach ($probe['errors'] as $err) {
                $this->line('  • '.(string) $err);
            }
            if ($probe['errors'] === []) {
                $this->line('  (порожня відповідь без пояснення — перевірте мережу та ключ.)');
            }
            $this->line('');
            $this->line('Переконайтесь, що вставлено повний ключ UUID (~36 символів) з my.novaposhta.ua → Безпека → API 2.0.');

            return self::FAILURE;
        }

        $first = $cities[0];
        $cityRef = (string) ($first['Ref'] ?? '');
        $cityLabel = (string) ($first['Description'] ?? $first['Present'] ?? $cityRef);
        $this->info('Місто (приклад): '.$cityLabel);
        $this->line('  Ref: '.$cityRef);

        $warehouses = $client->warehousesBranches($cityRef);
        $n = count($warehouses);
        $this->info('Відділень знайдено: '.$n);
        $slice = array_slice($warehouses, 0, 3);
        foreach ($slice as $row) {
            $this->line('  — '.(string) ($row['Description'] ?? '?'));
        }
        if ($n > 3) {
            $this->line('  …');
        }

        $this->line('');
        $this->info('Чекаут підтягуватиме ті самі довідники, якщо ключ у .env збігається.');

        return self::SUCCESS;
    }
}
