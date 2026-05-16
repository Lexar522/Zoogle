<?php

namespace App\Support;

use App\Models\ShopFooterBrand;
use App\Models\ShopFooterColumn;
use App\Models\ShopInfoPage;
use App\Models\ShopIntegrationSetting;

final class ShopFooterViewData
{
    /** Той самий файл, що й у шапці вітрини (`partials/shop-header`). */
    private const STOREFRONT_LOGO_PATH = 'images/zoogle-logo-new.png';

    /**
     * @return array{
     *   use_dynamic_columns: bool,
     *   link_column_count: int,
     *   brand: array{body: ?string, phone: ?string, site_title: ?string, logo_url: string},
     *   columns: list<array{title: string, links: list<array{label: string, href: string, open_new_tab: bool}>}>,
     *   info_pages: list<array{slug: string, title: string}>,
     *   pickup_map: array{address: ?string, embed_url: ?string, maps_url: ?string, has_embed: bool, embed_provider: ?string}
     * }
     */
    public static function forRequest(): array
    {
        $brandRow = ShopFooterBrand::query()->first();
        $settings = ShopIntegrationSetting::record();

        $columns = ShopFooterColumn::query()
            ->with('links')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $mappedColumns = [];
        foreach ($columns as $col) {
            $title = self::localized($col->title_uk, $col->title_en, $col->title_ru);
            $links = [];
            foreach ($col->links as $link) {
                $label = self::localized($link->label_uk, $link->label_en, $link->label_ru);
                if (trim($label) === '' || trim($link->url) === '') {
                    continue;
                }
                $links[] = [
                    'label' => $label,
                    'href' => self::normalizeHref((string) $link->url),
                    'open_new_tab' => (bool) $link->open_new_tab,
                ];
            }
            if ($title !== '' || $links !== []) {
                $mappedColumns[] = [
                    'title' => $title,
                    'links' => $links,
                ];
            }
        }

        $useDynamic = $mappedColumns !== [];

        $infoPages = ShopInfoPage::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static function (ShopInfoPage $p): array {
                $title = $p->titleForLocale();

                return [
                    'slug' => $p->slug,
                    'title' => $title !== '' ? $title : $p->slug,
                ];
            })
            ->values()
            ->all();

        $logoUrl = asset(self::STOREFRONT_LOGO_PATH);

        $pickupAddress = self::nullableTrim($settings?->pickup_address);
        $lat = $settings?->pickup_lat;
        $lng = $settings?->pickup_lng;
        $hasCoords = $lat !== null && $lng !== null
            && is_numeric($lat) && is_numeric($lng);
        $embedUrl = null;
        $embedProvider = null;
        if ($hasCoords) {
            $la = (float) $lat;
            $ln = (float) $lng;
            $coordsOk = is_finite($la) && is_finite($ln);
            if ($coordsOk) {
                $gmapsKey = app(GoogleMapsApiKey::class)->current();
                if ($gmapsKey !== '') {
                    // Режим place: обов'язковий q (адреса або lat,lng з URL-escape — див. Maps Embed API).
                    $q = ($pickupAddress !== null && $pickupAddress !== '')
                        ? $pickupAddress
                        : sprintf('%F,%F', $la, $ln);
                    $embedUrl = 'https://www.google.com/maps/embed/v1/place?key='.rawurlencode($gmapsKey)
                        .'&q='.rawurlencode($q)
                        .'&zoom=15';
                    $embedProvider = 'google';
                } else {
                    $padLon = 0.018;
                    $padLat = 0.012;
                    $minLon = $ln - $padLon;
                    $maxLon = $ln + $padLon;
                    $minLat = $la - $padLat;
                    $maxLat = $la + $padLat;
                    $embedUrl = sprintf(
                        'https://www.openstreetmap.org/export/embed.html?bbox=%.7F,%.7F,%.7F,%.7F&layer=mapnik&marker=%.7F,%.7F',
                        $minLon,
                        $minLat,
                        $maxLon,
                        $maxLat,
                        $la,
                        $ln
                    );
                    $embedProvider = 'osm';
                }
            }
        }
        $mapsUrl = null;
        if ($hasCoords) {
            $mapsUrl = 'https://www.google.com/maps?q='.rawurlencode((string) $lat.','.(string) $lng);
        } elseif ($pickupAddress !== null) {
            $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($pickupAddress);
        }

        return [
            'use_dynamic_columns' => $useDynamic,
            'link_column_count' => max(1, count($mappedColumns)),
            'brand' => [
                'body' => $brandRow?->body,
                'phone' => $brandRow?->phone,
                'site_title' => $brandRow?->site_title,
                'logo_url' => $logoUrl,
            ],
            'columns' => $mappedColumns,
            'info_pages' => $infoPages,
            'pickup_map' => [
                'address' => $pickupAddress,
                'embed_url' => $embedUrl,
                'maps_url' => $mapsUrl,
                'has_embed' => $embedUrl !== null,
                'embed_provider' => $embedProvider,
            ],
        ];
    }

    private static function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $t = trim($value);

        return $t === '' ? null : $t;
    }

    private static function localized(?string $uk, ?string $en, ?string $ru): string
    {
        $locale = app()->getLocale();

        return match ($locale) {
            'en' => self::firstNonEmpty($en, $uk, $ru),
            'ru' => self::firstNonEmpty($ru, $uk, $en),
            default => self::firstNonEmpty($uk, $en, $ru),
        };
    }

    private static function firstNonEmpty(?string ...$candidates): string
    {
        foreach ($candidates as $c) {
            $t = trim((string) $c);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    private static function normalizeHref(string $raw): string
    {
        $url = trim($raw);
        if ($url === '') {
            return '#';
        }

        if (str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return '/'.$url;
    }
}
