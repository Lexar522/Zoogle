<?php

namespace App\Services\NovaPoshta;

use App\Support\NovaPoshtaApiKey;
use Illuminate\Support\Facades\Http;

class NovaPoshtaClient
{
    public function __construct(
        private readonly NovaPoshtaApiKey $apiKeyResolver,
    ) {}

    public const string ENDPOINT = 'https://api.novaposhta.ua/v2.0/json/';

    /** Поштове відділення */
    public const string TYPE_POST_OFFICE = '841339c7-591a-42e2-8233-7a0a00f0ed6f';

    /** Поштомат */
    public const string TYPE_POSTOMAT = '95dc212d-479c-4ffb-a8ab-8c1b9073d0bc';

    public function isConfigured(): bool
    {
        return $this->apiKeyResolver->isConfigured();
    }

    /**
     * @return array{success: bool, data: array<int, mixed>, errors: array<int, mixed>}
     */
    public function call(string $modelName, string $calledMethod, array $methodProperties = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'data' => [], 'errors' => ['Nova Poshta API key is not configured.']];
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->post(self::ENDPOINT, [
                'apiKey' => $this->apiKeyResolver->current(),
                'modelName' => $modelName,
                'calledMethod' => $calledMethod,
                // НП очікує object; порожній PHP-масив JSON-кодується як [] і може ламати getAreas.
                'methodProperties' => $methodProperties === [] ? new \stdClass : $methodProperties,
            ]);

        $json = $response->json();
        if (! is_array($json)) {
            if (! $response->successful()) {
                return ['success' => false, 'data' => [], 'errors' => ['HTTP '.$response->status()]];
            }

            return ['success' => false, 'data' => [], 'errors' => ['Invalid JSON response.']];
        }

        $npErrors = array_values((array) ($json['errors'] ?? []));
        $data = array_values((array) ($json['data'] ?? []));

        // НП часто відповідає 401 з тілом JSON { success: false, errors: [...] } — не губимо текст помилки.
        if (! $response->successful()) {
            return [
                'success' => false,
                'data' => [],
                'errors' => $npErrors !== [] ? $npErrors : ['HTTP '.$response->status()],
            ];
        }

        return [
            'success' => (bool) ($json['success'] ?? false),
            'data' => $data,
            'errors' => $npErrors,
        ];
    }

    /**
     * Список областей / регіонів (усі сторінки довідника).
     *
     * @return list<array<string, mixed>>
     */
    public function listAreas(): array
    {
        /** @var array<string, array<string, mixed>> $byRef унікальний Ref (НП іноді дублює записи між сторінками) */
        $byRef = [];

        $first = $this->call('Address', 'getAreas', []);
        if ($first['success'] && $first['data'] !== []) {
            foreach ($first['data'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $ref = (string) ($row['Ref'] ?? '');
                if ($ref === '') {
                    continue;
                }
                $byRef[$ref] = $row;
            }
        }

        if ($byRef !== []) {
            return array_values($byRef);
        }

        for ($page = 0; $page < 50; $page++) {
            $result = $this->call('Address', 'getAreas', [
                'Page' => (string) $page,
            ]);
            if (! $result['success']) {
                break;
            }
            $chunk = $result['data'];
            if ($chunk === []) {
                break;
            }
            foreach ($chunk as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $ref = (string) ($row['Ref'] ?? '');
                if ($ref === '') {
                    continue;
                }
                $byRef[$ref] = $row;
            }
        }

        return array_values($byRef);
    }

    /**
     * Один населений пункт довідника «міста компанії» за Ref (для префілу на чекауті).
     *
     * @return array<string, mixed>|null
     */
    public function getCityByRef(string $cityRef): ?array
    {
        $cityRef = trim($cityRef);
        if ($cityRef === '') {
            return null;
        }

        $result = $this->call('Address', 'getCities', [
            'Ref' => $cityRef,
        ]);

        if (! $result['success'] || $result['data'] === []) {
            return null;
        }

        $first = $result['data'][0];

        return is_array($first) ? $first : null;
    }

    /**
     * Усі міста довідника НП у межах області (за полем Area / AreaRef у API).
     *
     * @return list<array<string, mixed>>
     */
    public function citiesInArea(string $areaRef): array
    {
        $areaRef = trim($areaRef);
        if ($areaRef === '') {
            return [];
        }

        $byRef = [];

        for ($page = 0; $page < 50; $page++) {
            $result = $this->call('Address', 'getCities', [
                'AreaRef' => $areaRef,
                'Page' => (string) $page,
            ]);

            if (! $result['success']) {
                break;
            }

            $chunk = $result['data'];
            if ($chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $ref = (string) ($row['Ref'] ?? '');
                if ($ref === '') {
                    continue;
                }
                $byRef[$ref] = $row;
            }
        }

        if ($byRef !== []) {
            return array_values($byRef);
        }

        for ($page = 0; $page < 50; $page++) {
            $result = $this->call('Address', 'getCities', [
                'FindByString' => '',
                'Page' => (string) $page,
            ]);

            if (! $result['success']) {
                break;
            }

            $chunk = $result['data'];
            if ($chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if ((string) ($row['Area'] ?? '') !== $areaRef) {
                    continue;
                }
                $ref = (string) ($row['Ref'] ?? '');
                if ($ref === '') {
                    continue;
                }
                $byRef[$ref] = $row;
            }
        }

        return array_values($byRef);
    }

    /**
     * @return list<array{Ref: string, Description: string, ...}>
     */
    public function searchCities(string $findByString, int $page = 0): array
    {
        $findByString = trim($findByString);
        if ($findByString === '') {
            return [];
        }

        $result = $this->call('Address', 'getCities', [
            'FindByString' => $findByString,
            'Page' => (string) $page,
        ]);

        if (! $result['success']) {
            return [];
        }

        return $result['data'];
    }

    /**
     * Відділення / поштомати в місті (усі сторінки довідника).
     *
     * @return list<array<string, mixed>>
     */
    public function warehousesForCity(string $cityRef, ?string $typeOfWarehouse = null, int $maxPages = 40): array
    {
        $cityRef = trim($cityRef);
        if ($cityRef === '') {
            return [];
        }

        $merged = $this->fetchWarehousePages($cityRef, $typeOfWarehouse, $maxPages);

        // Якщо фільтр за типом дав порожньо або помилку — тягнемо повний список і відсіюємо локально.
        if ($typeOfWarehouse !== null && $typeOfWarehouse !== '' && $merged === []) {
            $merged = $this->fetchWarehousePages($cityRef, null, $maxPages);
        }

        if ($typeOfWarehouse !== null && $typeOfWarehouse !== '') {
            $merged = array_values(array_filter(
                $merged,
                fn (array $row): bool => (string) ($row['TypeOfWarehouse'] ?? '') === $typeOfWarehouse
            ));
        }

        return $merged;
    }

    /**
     * Відділення для вітрини: спочатку тип «поштове відділення», інакше всі пункти, крім поштоматів.
     *
     * @return list<array<string, mixed>>
     */
    public function warehousesBranches(string $cityRef): array
    {
        $rows = $this->warehousesForCity($cityRef, self::TYPE_POST_OFFICE);
        if ($rows !== []) {
            return $rows;
        }

        return $this->warehousesExcludingPostomats($cityRef);
    }

    /**
     * Усі відділення/пункти в місті, окрім поштоматів (якщо довідник не відфільтрувався за Ref типу).
     *
     * @return list<array<string, mixed>>
     */
    public function warehousesExcludingPostomats(string $cityRef, int $maxPages = 40): array
    {
        $all = $this->fetchWarehousePages($cityRef, null, $maxPages);

        return array_values(array_filter(
            $all,
            fn (array $row): bool => (string) ($row['TypeOfWarehouse'] ?? '') !== self::TYPE_POSTOMAT
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchWarehousePages(string $cityRef, ?string $typeOfWarehouse, int $maxPages): array
    {
        $merged = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $props = [
                'CityRef' => $cityRef,
                'Page' => (string) $page,
            ];
            if ($typeOfWarehouse !== null && $typeOfWarehouse !== '') {
                $props['TypeOfWarehouse'] = $typeOfWarehouse;
            }

            $result = $this->call('Address', 'getWarehouses', $props);
            if (! $result['success']) {
                break;
            }

            $chunk = $result['data'];
            if ($chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $merged[] = $row;
                }
            }
        }

        return $merged;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchStreets(string $cityRef, string $findByString, int $page = 0): array
    {
        $cityRef = trim($cityRef);
        $findByString = trim($findByString);
        if ($cityRef === '' || $findByString === '') {
            return [];
        }

        $result = $this->call('Address', 'getStreet', [
            'CityRef' => $cityRef,
            'FindByString' => $findByString,
            'Page' => (string) $page,
        ]);

        if (! $result['success']) {
            return [];
        }

        return $result['data'];
    }
}
