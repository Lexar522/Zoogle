<?php

namespace App\Models;

use App\Support\SchemaCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Бургер-меню на телефоні: пункти (порядок, назва, колір, група) і блоки контактів та входу.
 */
class ShopMobileMenuSetting extends Model
{
    public const TARGET_CUSTOM = 'custom';

    /** @var array<string, string> куди веде пункт → підпис в адмінці */
    public const TARGETS = [
        'catalog' => 'Каталог',
        'on_sale' => 'Акції',
        'bundles' => 'Комплекти',
        'care' => 'Поради по догляду',
        'sponsorship' => 'Віртуальна опіка',
        'charity' => 'Благодійність',
        'delivery' => 'Доставка та оплата',
        'about' => 'Про нас',
        'contacts' => 'Сторінка «Контакти»',
        'news' => 'Новини',
        'privacy' => 'Конфіденційність',
        self::TARGET_CUSTOM => 'Своє посилання',
    ];

    /** @var array<string, string> */
    public const COLORS = [
        'default' => 'Звичайний (чорний)',
        'blue' => 'Синій',
        'green' => 'Зелений',
        'pink' => 'Малиновий',
        'red' => 'Червоний',
    ];

    /** @var array<string, string> */
    public const GROUPS = [
        'main' => 'Основні пункти',
        'info' => 'Розділ «Інформація»',
    ];

    /** Меню за замовчуванням — як було до налаштування в адмінці. */
    public const DEFAULT_ITEMS = [
        ['target' => 'catalog', 'label' => null, 'color' => 'default', 'group' => 'main', 'url' => null],
        ['target' => 'on_sale', 'label' => null, 'color' => 'default', 'group' => 'main', 'url' => null],
        ['target' => 'bundles', 'label' => null, 'color' => 'default', 'group' => 'main', 'url' => null],
        ['target' => 'care', 'label' => null, 'color' => 'blue', 'group' => 'main', 'url' => null],
        ['target' => 'sponsorship', 'label' => null, 'color' => 'green', 'group' => 'main', 'url' => null],
        ['target' => 'charity', 'label' => null, 'color' => 'pink', 'group' => 'main', 'url' => null],
        ['target' => 'delivery', 'label' => null, 'color' => 'default', 'group' => 'info', 'url' => null],
        ['target' => 'about', 'label' => null, 'color' => 'default', 'group' => 'info', 'url' => null],
    ];

    private const MEMO_KEY = 'mobile-menu-settings';

    protected $fillable = [
        'items',
        'show_contacts',
        'show_login',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'show_contacts' => 'boolean',
            'show_login' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetResolvedCache());
        static::deleted(fn () => self::forgetResolvedCache());
    }

    /**
     * Готове до показу меню: лише пункти з дійсними посиланнями й увімкненими розділами.
     *
     * @return array{main: list<array{label: string, url: string, color: string}>, info: list<array{label: string, url: string, color: string}>, show_contacts: bool, show_login: bool}
     */
    public static function resolved(): array
    {
        return StorefrontMemo::remember(self::MEMO_KEY, function (): array {
            $row = SchemaCache::hasTable('shop_mobile_menu_settings')
                ? self::query()->orderBy('id')->first()
                : null;
            $items = is_array($row?->items) && $row->items !== [] ? $row->items : self::DEFAULT_ITEMS;

            $menu = ['main' => [], 'info' => []];
            foreach ($items as $item) {
                $link = self::linkFor(is_array($item) ? $item : []);
                if ($link !== null) {
                    $menu[$link['group']][] = ['label' => $link['label'], 'url' => $link['url'], 'color' => $link['color']];
                }
            }

            return [
                ...$menu,
                'show_contacts' => $row?->show_contacts ?? true,
                'show_login' => $row?->show_login ?? true,
            ];
        });
    }

    /**
     * Безпечне власне посилання: шлях сайту (/...) або повна адреса https.
     */
    public static function isAllowedCustomUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    public static function forgetResolvedCache(): void
    {
        StorefrontMemo::forget(self::MEMO_KEY);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, url: string, color: string, group: string}|null
     */
    private static function linkFor(array $item): ?array
    {
        $target = (string) ($item['target'] ?? '');
        if (! isset(self::TARGETS[$target])) {
            return null;
        }

        [$url, $defaultLabel] = match ($target) {
            'catalog' => [route('catalog.index'), __('shop.mobile_menu_catalog')],
            'on_sale' => [route('catalog.index', ['on_sale' => 1]), __('shop.mobile_menu_on_sale')],
            'bundles' => [route('bundles.index'), __('shop.mobile_menu_bundles')],
            'care' => [route('care.index'), __('shop.home_care_title')],
            'sponsorship' => ShopSponsorshipSetting::isEnabled()
                ? [route('sponsorship.index'), ShopSponsorshipSetting::text('header_label')]
                : [null, ''],
            'charity' => ShopCharitySetting::isEnabled()
                ? [route('charity'), ShopCharitySetting::resolved()['header_label']]
                : [null, ''],
            'delivery' => [route('delivery-payment'), __('shop.footer_delivery')],
            'about' => [route('about'), __('shop.footer_about')],
            'contacts' => [route('contacts'), __('shop.footer_contacts')],
            'news' => [route('news'), __('shop.footer_news')],
            'privacy' => [route('privacy'), __('shop.footer_privacy')],
            self::TARGET_CUSTOM => [self::isAllowedCustomUrl($item['url'] ?? null) ? trim((string) $item['url']) : null, ''],
        };

        $label = trim((string) ($item['label'] ?? '')) ?: $defaultLabel;
        if ($url === null || $label === '') {
            return null;
        }

        $color = (string) ($item['color'] ?? 'default');
        $group = (string) ($item['group'] ?? 'main');

        return [
            'label' => $label,
            'url' => $url,
            'color' => isset(self::COLORS[$color]) ? $color : 'default',
            'group' => isset(self::GROUPS[$group]) ? $group : 'main',
        ];
    }
}
