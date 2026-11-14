<?php

namespace App\Models;

use App\Support\HomeBenefitStyles;
use App\Support\SchemaCache;
use App\Support\StorefrontViewCache;
use Illuminate\Database\Eloquent\Model;

class ShopHomeBenefit extends Model
{
    protected $fillable = [
        'is_enabled',
        'title',
        'lead',
        'items',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'items' => 'array',
        ];
    }

    /**
     * Дані для головної. Поки запису немає — поточні тексти з перекладів.
     *
     * @return array{
     *     enabled: bool,
     *     title: string,
     *     lead: string,
     *     items: list<array{title: string, text: string, color: string, style: string}>
     * }
     */
    public static function forStorefront(): array
    {
        return StorefrontViewCache::remember('home-benefits', fn (): array => self::buildForStorefront());
    }

    private static function buildForStorefront(): array
    {
        if (! SchemaCache::hasTable('shop_home_benefits')) {
            return self::fallbackPayload();
        }

        $row = self::query()->orderBy('id')->first();
        if ($row === null) {
            return self::fallbackPayload();
        }

        return [
            'enabled' => (bool) $row->is_enabled,
            'title' => trim((string) ($row->title ?? '')),
            'lead' => trim((string) ($row->lead ?? '')),
            'items' => self::presentItems(is_array($row->items) ? $row->items : []),
        ];
    }

    /**
     * @return array{is_enabled: bool, title: string, lead: string, items: list<array{title: string, text: string, color: string}>}
     */
    public static function formDefaults(): array
    {
        $fallback = self::fallbackPayload();

        return [
            'is_enabled' => true,
            'title' => $fallback['title'],
            'lead' => $fallback['lead'],
            'items' => array_map(
                static fn (array $item): array => [
                    'title' => $item['title'],
                    'text' => $item['text'],
                    'color' => $item['color'],
                ],
                $fallback['items']
            ),
        ];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{title: string, text: string, color: string}>
     */
    public static function sanitizeItems(array $items): array
    {
        $clean = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }

            $fallback = HomeBenefitStyles::DEFAULTS[$index % count(HomeBenefitStyles::DEFAULTS)];
            $clean[] = [
                'title' => mb_substr($title, 0, 255),
                'text' => mb_substr($text, 0, 2000),
                'color' => HomeBenefitStyles::normalizeHex((string) ($item['color'] ?? ''), $fallback),
            ];
        }

        return $clean;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     title: string,
     *     lead: string,
     *     items: list<array{title: string, text: string, color: string, style: string}>
     * }
     */
    private static function fallbackPayload(): array
    {
        $items = [];
        foreach (HomeBenefitStyles::DEFAULTS as $index => $color) {
            $n = $index + 1;
            $items[] = [
                'title' => (string) __('shop.home_benefit_'.$n.'_title'),
                'text' => (string) __('shop.home_benefit_'.$n.'_text'),
                'color' => $color,
            ];
        }

        return [
            'enabled' => true,
            'title' => (string) __('shop.home_benefits_title'),
            'lead' => (string) __('shop.home_benefits_lead'),
            'items' => self::presentItems($items),
        ];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{title: string, text: string, color: string, style: string}>
     */
    private static function presentItems(array $items): array
    {
        $presented = [];
        foreach (self::sanitizeItems($items) as $item) {
            $presented[] = [
                'title' => $item['title'],
                'text' => $item['text'],
                'color' => $item['color'],
                'style' => HomeBenefitStyles::cssVars($item['color']),
            ];
        }

        return $presented;
    }
}
