<?php

namespace App\Http\Controllers;

use App\Services\NovaPoshta\NovaPoshtaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NovaPoshtaApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{lat: float|null, lng: float|null}
     */
    private static function cityPoint(array $row): array
    {
        $lat = self::npFloat($row['Latitude'] ?? $row['latitude'] ?? null);
        $lng = self::npFloat($row['Longitude'] ?? $row['longitude'] ?? null);
        if ($lat === null || $lng === null) {
            $loc = $row['Location'] ?? $row['location'] ?? null;
            if (is_array($loc)) {
                $lat = $lat ?? self::npFloat($loc['Latitude'] ?? $loc['lat'] ?? null);
                $lng = $lng ?? self::npFloat($loc['Longitude'] ?? $loc['lon'] ?? $loc['Lng'] ?? null);
            }
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{lat: float|null, lng: float|null}
     */
    private static function warehousePoint(array $row): array
    {
        $lat = self::npFloat($row['Latitude'] ?? $row['latitude'] ?? null);
        $lng = self::npFloat($row['Longitude'] ?? $row['longitude'] ?? null);
        if ($lat === null || $lng === null) {
            $loc = $row['Location'] ?? $row['location'] ?? null;
            if (is_array($loc)) {
                $lat = $lat ?? self::npFloat($loc['Latitude'] ?? $loc['lat'] ?? null);
                $lng = $lng ?? self::npFloat($loc['Longitude'] ?? $loc['lon'] ?? $loc['Lng'] ?? null);
            }
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    private static function npFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }
        $f = filter_var((string) $v, FILTER_VALIDATE_FLOAT);

        return $f !== false ? $f : null;
    }

    public function areas(NovaPoshtaClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Nova Poshta API is not configured.', 'data' => []], 503);
        }

        $rows = $client->listAreas();
        if ($rows === []) {
            $probe = $client->call('Address', 'getAreas', []);
            if (! $probe['success']) {
                return response()->json([
                    'message' => 'Nova Poshta API error.',
                    'errors' => $probe['errors'],
                    'data' => [],
                ], 502);
            }
        }

        $data = [];
        $seenLabels = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ref = (string) ($row['Ref'] ?? '');
            $label = (string) ($row['Description'] ?? $row['Present'] ?? '');
            if ($ref === '' || $label === '') {
                continue;
            }
            $labelKey = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label)));
            if (isset($seenLabels[$labelKey])) {
                continue;
            }
            $seenLabels[$labelKey] = true;
            $data[] = ['ref' => $ref, 'label' => $label];
        }

        usort($data, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return response()->json(['data' => $data]);
    }

    public function cityByRef(Request $request, NovaPoshtaClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Nova Poshta API is not configured.', 'data' => null], 503);
        }

        $ref = trim((string) $request->query('ref', ''));
        if (mb_strlen($ref) !== 36) {
            return response()->json(['data' => null], 422);
        }

        $row = $client->getCityByRef($ref);
        if ($row === null) {
            return response()->json(['data' => null], 404);
        }

        $label = (string) ($row['Description'] ?? $row['Present'] ?? '');
        $areaRef = (string) ($row['Area'] ?? '');
        $pt = self::cityPoint($row);

        return response()->json([
            'data' => [
                'ref' => $ref,
                'label' => $label,
                'area_ref' => $areaRef,
                'lat' => $pt['lat'],
                'lng' => $pt['lng'],
            ],
        ]);
    }

    public function citiesByArea(Request $request, NovaPoshtaClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Nova Poshta API is not configured.', 'data' => []], 503);
        }

        $areaRef = trim((string) $request->query('area_ref', ''));
        if (mb_strlen($areaRef) !== 36) {
            return response()->json(['data' => []], 422);
        }

        $rows = $client->citiesInArea($areaRef);
        $data = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ref = (string) ($row['Ref'] ?? '');
            $label = (string) ($row['Description'] ?? $row['Present'] ?? '');
            if ($ref === '' || $label === '') {
                continue;
            }
            $pt = self::cityPoint($row);
            $data[] = [
                'ref' => $ref,
                'label' => $label,
                'lat' => $pt['lat'],
                'lng' => $pt['lng'],
            ];
        }

        usort($data, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return response()->json(['data' => $data]);
    }

    public function cities(Request $request, NovaPoshtaClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Nova Poshta API is not configured.', 'data' => []], 503);
        }

        $q = trim((string) $request->query('q', ''));
        $page = max(0, (int) $request->query('page', 0));
        $areaRef = trim((string) $request->query('area_ref', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        if ($areaRef !== '' && mb_strlen($areaRef) !== 36) {
            return response()->json(['data' => []], 422);
        }

        $rows = $client->searchCities($q, $page);
        $data = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if ($areaRef !== '' && (string) ($row['Area'] ?? '') !== $areaRef) {
                continue;
            }
            $ref = (string) ($row['Ref'] ?? '');
            $label = (string) ($row['Description'] ?? $row['Present'] ?? '');
            if ($ref === '' || $label === '') {
                continue;
            }
            $pt = self::cityPoint($row);
            $data[] = [
                'ref' => $ref,
                'label' => $label,
                'lat' => $pt['lat'],
                'lng' => $pt['lng'],
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function warehouses(Request $request, NovaPoshtaClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Nova Poshta API is not configured.', 'data' => []], 503);
        }

        $cityRef = trim((string) $request->query('city_ref', ''));
        if ($cityRef === '') {
            return response()->json(['data' => []], 422);
        }

        $kind = (string) $request->query('kind', 'branch');
        $rows = $kind === 'locker'
            ? $client->warehousesForCity($cityRef, NovaPoshtaClient::TYPE_POSTOMAT)
            : $client->warehousesBranches($cityRef);
        $data = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ref = (string) ($row['Ref'] ?? '');
            $label = (string) ($row['Description'] ?? '');
            if ($ref === '' || $label === '') {
                continue;
            }
            $pt = self::warehousePoint($row);
            $data[] = [
                'ref' => $ref,
                'label' => $label,
                'number' => $row['Number'] ?? null,
                'lat' => $pt['lat'],
                'lng' => $pt['lng'],
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function streets(Request $request, NovaPoshtaClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json(['message' => 'Nova Poshta API is not configured.', 'data' => []], 503);
        }

        $cityRef = trim((string) $request->query('city_ref', ''));
        $q = trim((string) $request->query('q', ''));
        $page = max(0, (int) $request->query('page', 0));

        if ($cityRef === '' || mb_strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $rows = $client->searchStreets($cityRef, $q, $page);
        $data = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $ref = (string) ($row['Ref'] ?? '');
            $label = (string) ($row['Description'] ?? $row['Present'] ?? '');
            if ($ref === '' || $label === '') {
                continue;
            }
            $data[] = ['ref' => $ref, 'label' => $label];
        }

        return response()->json(['data' => $data]);
    }
}
