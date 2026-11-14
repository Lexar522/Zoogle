<?php

namespace App\Models;

use App\Support\SchemaCache;
use App\Support\StorefrontViewCache;
use Illuminate\Database\Eloquent\Model;

class ShopHomeProductSection extends Model
{
    protected $fillable = [
        'hits_enabled',
        'hits_title',
        'hits_lead',
        'recommended_enabled',
        'recommended_title',
        'recommended_lead',
    ];

    protected function casts(): array
    {
        return [
            'hits_enabled' => 'boolean',
            'recommended_enabled' => 'boolean',
        ];
    }

    /**
     * @return array{
     *     hits: array{enabled: bool, title: string, lead: string},
     *     recommended: array{enabled: bool, title: string, lead: string}
     * }
     */
    public static function forStorefront(): array
    {
        return StorefrontViewCache::remember('home-product-sections', fn (): array => self::buildForStorefront());
    }

    private static function buildForStorefront(): array
    {
        if (! SchemaCache::hasTable('shop_home_product_sections')) {
            return self::fallbackPayload();
        }

        $row = self::query()->orderBy('id')->first();
        if ($row === null) {
            return self::fallbackPayload();
        }

        return [
            'hits' => self::present(
                (bool) $row->hits_enabled,
                (string) ($row->hits_title ?? ''),
                (string) ($row->hits_lead ?? ''),
                'shop.home_hits_title'
            ),
            'recommended' => self::present(
                (bool) $row->recommended_enabled,
                (string) ($row->recommended_title ?? ''),
                (string) ($row->recommended_lead ?? ''),
                'shop.home_rec_title'
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(): array
    {
        $fallback = self::fallbackPayload();

        return [
            'hits_enabled' => true,
            'hits_title' => $fallback['hits']['title'],
            'hits_lead' => $fallback['hits']['lead'],
            'recommended_enabled' => true,
            'recommended_title' => $fallback['recommended']['title'],
            'recommended_lead' => $fallback['recommended']['lead'],
        ];
    }

    /**
     * @return array{
     *     hits: array{enabled: bool, title: string, lead: string},
     *     recommended: array{enabled: bool, title: string, lead: string}
     * }
     */
    private static function fallbackPayload(): array
    {
        return [
            'hits' => [
                'enabled' => true,
                'title' => (string) __('shop.home_hits_title'),
                'lead' => (string) __('shop.home_hits_lead'),
            ],
            'recommended' => [
                'enabled' => true,
                'title' => (string) __('shop.home_rec_title'),
                'lead' => (string) __('shop.home_rec_lead'),
            ],
        ];
    }

    /**
     * @return array{enabled: bool, title: string, lead: string}
     */
    private static function present(bool $enabled, string $title, string $lead, string $titleKey): array
    {
        $title = trim($title);

        return [
            'enabled' => $enabled,
            'title' => $title !== '' ? mb_substr($title, 0, 255) : (string) __($titleKey),
            'lead' => mb_substr(trim($lead), 0, 1000),
        ];
    }
}
