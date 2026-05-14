<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    /**
     * Пошук центру міста (OpenStreetMap Nominatim). Використовується лише для карти на чекауті.
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function city(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['lat' => null, 'lng' => null], 422);
        }

        $ua = trim((string) config('app.name', 'ZOOGLE')).' checkout; '.trim((string) config('app.url', 'http://localhost'));

        $response = Http::timeout(12)
            ->withHeaders([
                'User-Agent' => $ua,
                'Accept-Language' => 'uk,en',
            ])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $q.', Ukraine',
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0,
            ]);

        if (! $response->successful()) {
            return response()->json(['lat' => null, 'lng' => null], 502);
        }

        $json = $response->json();
        if (! is_array($json) || $json === []) {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        $first = $json[0];
        if (! is_array($first)) {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        $lat = isset($first['lat']) ? filter_var($first['lat'], FILTER_VALIDATE_FLOAT) : false;
        $lon = isset($first['lon']) ? filter_var($first['lon'], FILTER_VALIDATE_FLOAT) : false;

        return response()->json([
            'lat' => $lat !== false ? $lat : null,
            'lng' => $lon !== false ? $lon : null,
        ]);
    }

    /**
     * Пошук за повною адресою (місто + вулиця тощо) через Nominatim.
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function address(Request $request): JsonResponse
    {
        $q = trim(preg_replace('/\s+/u', ' ', (string) $request->query('q', '')));
        if (mb_strlen($q) < 2) {
            return response()->json(['lat' => null, 'lng' => null, 'address' => null], 422);
        }

        $ua = trim((string) config('app.name', 'ZOOGLE')).' checkout; '.trim((string) config('app.url', 'http://localhost'));

        $searchParams = [
            'q' => $q,
            'format' => 'json',
            'limit' => 10,
            'addressdetails' => 1,
            'countrycodes' => 'ua',
        ];

        $contact = config('mail.from.address');
        if (is_string($contact) && trim($contact) !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $searchParams['email'] = trim($contact);
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => $ua,
                'Accept-Language' => 'uk,en',
            ])
            ->get('https://nominatim.openstreetmap.org/search', $searchParams);

        if (! $response->successful()) {
            return response()->json(['lat' => null, 'lng' => null, 'address' => null], 502);
        }

        $json = $response->json();
        $first = $this->firstNominatimSearchHit(is_array($json) ? $json : []);

        if ($first === null && str_contains(mb_strtolower($q), 'ukraine') === false && str_contains(mb_strtolower($q), 'україн') === false) {
            $searchParams['q'] = $q.', Ukraine';
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => $ua,
                    'Accept-Language' => 'uk,en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', $searchParams);

            if ($response->successful()) {
                $json = $response->json();
                $first = $this->firstNominatimSearchHit(is_array($json) ? $json : []);
            }
        }

        if ($first === null) {
            return response()->json(['lat' => null, 'lng' => null, 'address' => null]);
        }

        $lat = isset($first['lat']) ? filter_var($first['lat'], FILTER_VALIDATE_FLOAT) : false;
        $lon = isset($first['lon']) ? filter_var($first['lon'], FILTER_VALIDATE_FLOAT) : false;

        $shortAddress = $this->formatCourierAddressFromNominatim($first);

        return response()->json([
            'lat' => $lat !== false ? $lat : null,
            'lng' => $lon !== false ? $lon : null,
            'address' => $shortAddress,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function firstNominatimSearchHit(array $rows): ?array
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lat = isset($row['lat']) ? filter_var($row['lat'], FILTER_VALIDATE_FLOAT) : false;
            $lon = isset($row['lon']) ? filter_var($row['lon'], FILTER_VALIDATE_FLOAT) : false;
            if ($lat !== false && $lon !== false && ! is_nan($lat) && ! is_nan($lon)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Коротка адреса для поля доставки: вулиця, номер, місто, індекс, країна — без громад, районів, областей.
     *
     * @param  array<string, mixed>  $nominatim
     */
    private function formatCourierAddressFromNominatim(array $nominatim): ?string
    {
        $addr = $nominatim['address'] ?? null;
        if (! is_array($addr)) {
            return $this->shortAddressFromDisplayName($nominatim['display_name'] ?? null);
        }

        $house = $this->nominatimAddressPart($addr['house_number'] ?? null);
        $road = $this->nominatimPickRoad($addr);
        $city = $this->nominatimPickCity($addr);
        $postcode = $this->nominatimAddressPart($addr['postcode'] ?? null);

        $line1 = '';
        if ($road !== '' && $house !== '') {
            $line1 = $house.', '.$road;
        } elseif ($road !== '') {
            $line1 = $road;
        } elseif ($house !== '') {
            $line1 = $house;
        }

        $parts = [];
        if ($line1 !== '') {
            $parts[] = $line1;
        }
        if ($city !== '') {
            $parts[] = $city;
        }
        if ($postcode !== '') {
            $parts[] = $postcode;
        }

        if ($parts === []) {
            return $this->shortAddressFromDisplayName($nominatim['display_name'] ?? null);
        }

        $parts[] = 'Україна';

        return implode(', ', $parts);
    }

    private function nominatimAddressPart(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $s = trim((string) $value);

        return $s;
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    private function nominatimPickRoad(array $addr): string
    {
        foreach (['road', 'pedestrian', 'footway', 'residential', 'path', 'street', 'living_street'] as $key) {
            $s = $this->nominatimAddressPart($addr[$key] ?? null);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    private function nominatimPickCity(array $addr): string
    {
        foreach (['city', 'town', 'village'] as $key) {
            $s = $this->nominatimAddressPart($addr[$key] ?? null);
            if ($s !== '') {
                return $s;
            }
        }

        $m = $this->nominatimAddressPart($addr['municipality'] ?? null);
        if ($m !== '' && mb_stripos($m, 'громада') === false && mb_stripos($m, 'територіальна') === false) {
            return $m;
        }

        return '';
    }

    private function shortAddressFromDisplayName(mixed $displayName): ?string
    {
        if (! is_string($displayName)) {
            return null;
        }

        $displayName = trim($displayName);
        if ($displayName === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $displayName));
        $noiseNeedles = ['громада', 'район', 'область', 'hromada', 'raion', 'oblast'];
        $kept = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $lower = mb_strtolower($part);
            $noise = false;
            foreach ($noiseNeedles as $needle) {
                if (mb_strpos($lower, $needle) !== false) {
                    $noise = true;
                    break;
                }
            }
            if ($noise) {
                continue;
            }
            $kept[] = $part;
        }

        if ($kept === []) {
            return $displayName;
        }

        return implode(', ', array_slice($kept, 0, 6));
    }

    /**
     * Адреса за координатами (зворотне геокодування, Nominatim).
     *
     * @see https://operations.osmfoundation.org/policies/nominatim/
     */
    public function reverse(Request $request): JsonResponse
    {
        $latRaw = $request->query('lat');
        $lngRaw = $request->query('lng');
        $lat = is_numeric($latRaw) ? filter_var($latRaw, FILTER_VALIDATE_FLOAT) : false;
        $lng = is_numeric($lngRaw) ? filter_var($lngRaw, FILTER_VALIDATE_FLOAT) : false;

        if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json(['address' => null], 422);
        }

        $ua = trim((string) config('app.name', 'ZOOGLE')).' checkout; '.trim((string) config('app.url', 'http://localhost'));

        $response = Http::timeout(12)
            ->withHeaders([
                'User-Agent' => $ua,
                'Accept-Language' => 'uk,en',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

        if (! $response->successful()) {
            return response()->json(['address' => null], 502);
        }

        $data = $response->json();
        if (! is_array($data)) {
            return response()->json(['address' => null]);
        }

        $short = $this->formatCourierAddressFromNominatim($data);

        return response()->json([
            'address' => $short,
        ]);
    }
}
