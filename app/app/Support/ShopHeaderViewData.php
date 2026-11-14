<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ShopHeaderBanner;
use App\Models\ShopHeaderSetting;
use Illuminate\Support\Facades\Storage;

final class ShopHeaderViewData
{
    /**
     * @return array{left: ?int, right: ?int}
     */
    public static function featuredCategorySlots(): array
    {
        $settings = ShopHeaderSetting::storefront();
        $left = (int) ($settings->featured_left_option_value_id ?? 0);
        $right = (int) ($settings->featured_right_option_value_id ?? 0);

        return [
            'left' => $left > 0 ? $left : null,
            'right' => $right > 0 ? $right : null,
        ];
    }

    /**
     * Розміри hub-шапки для CSS-змінних на вітрині.
     *
     * @return array{
     *     featured_left_panel_max_height_px: int,
     *     featured_right_panel_max_height_px: int,
     *     banner_height_px: int
     * }
     */
    public static function layoutHeights(): array
    {
        $settings = ShopHeaderSetting::storefront();

        return [
            'featured_left_panel_max_height_px' => $settings->resolvedFeaturedLeftPanelMaxHeightPx(),
            'featured_right_panel_max_height_px' => $settings->resolvedFeaturedRightPanelMaxHeightPx(),
            'banner_height_px' => $settings->resolvedBannerHeightPx(),
        ];
    }

    /**
     * Inline CSS custom properties для стилів чіпів (порожньо = дефолтний вигляд).
     */
    public static function chipStylesInlineCss(): string
    {
        return ShopHeaderSetting::storefront()->chipStylesInlineCss();
    }

    /**
     * @return list<int>
     */
    public static function featuredCategoryIds(): array
    {
        $slots = self::featuredCategorySlots();

        return array_values(array_filter([
            $slots['left'],
            $slots['right'],
        ], fn (?int $id): bool => $id !== null && $id > 0));
    }

    /**
     * @return array{top: list<array{imageUrl: string, href: ?string, text: string, openNewTab: bool}>, bottom: list<array{imageUrl: string, href: ?string, text: string, openNewTab: bool}>}
     */
    public static function activeBannersBySlot(): array
    {
        return StorefrontViewCache::remember('header-banners', fn (): array => self::buildActiveBannersBySlot());
    }

    private static function buildActiveBannersBySlot(): array
    {
        $locale = app()->getLocale();
        $textColumn = self::localizedColumn('text', $locale);

        $rows = ShopHeaderBanner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $out = [
            ShopHeaderBanner::SLOT_TOP => [],
            ShopHeaderBanner::SLOT_BOTTOM => [],
        ];

        foreach ($rows as $row) {
            $slide = self::mapBannerSlide($row, $textColumn);
            if ($slide === null) {
                continue;
            }

            $slot = $row->slot === ShopHeaderBanner::SLOT_BOTTOM
                ? ShopHeaderBanner::SLOT_BOTTOM
                : ShopHeaderBanner::SLOT_TOP;
            $out[$slot][] = $slide;
        }

        return $out;
    }

    /**
     * @return list<array{imageUrl: string, href: ?string, text: string, openNewTab: bool}>
     */
    public static function activeBanners(): array
    {
        $bySlot = self::activeBannersBySlot();

        return array_merge($bySlot[ShopHeaderBanner::SLOT_TOP], $bySlot[ShopHeaderBanner::SLOT_BOTTOM]);
    }

    /**
     * @return array{imageUrl: string, href: ?string, text: string, openNewTab: bool}|null
     */
    private static function mapBannerSlide(ShopHeaderBanner $row, string $textColumn): ?array
    {
        $disk = Storage::disk('public');
        $path = StoragePublicPath::normalize($row->image_path);
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $imageUrl = ListingPhotoDerivative::bannerUrlForPath($path);
        if ($imageUrl === '') {
            return null;
        }

        $text = self::localizedValue($row, $textColumn, 'text_uk');

        return [
            'imageUrl' => $imageUrl,
            'href' => self::resolveBannerHref($row),
            'text' => $text,
            'openNewTab' => self::bannerOpensInNewTab($row),
        ];
    }

    public static function resolveBannerHref(ShopHeaderBanner $row): ?string
    {
        return match ($row->link_type) {
            ShopHeaderBanner::LINK_PRODUCT => self::productHref($row->product_id),
            ShopHeaderBanner::LINK_URL => self::urlHref($row->url),
            ShopHeaderBanner::LINK_CATEGORY => self::categoryHref($row->category_option_value_id),
            ShopHeaderBanner::LINK_ON_SALE => route('catalog.index', ['on_sale' => 1]),
            default => null,
        };
    }

    public static function bannerOpensInNewTab(ShopHeaderBanner $row): bool
    {
        if ($row->link_type !== ShopHeaderBanner::LINK_URL) {
            return false;
        }

        $href = trim((string) ($row->url ?? ''));

        return $href !== '' && preg_match('#^https?://#i', $href) === 1;
    }

    private static function productHref(mixed $productId): ?string
    {
        $id = (int) $productId;
        if ($id <= 0) {
            return null;
        }

        $slug = Product::query()->whereKey($id)->value('slug');
        if (! is_string($slug) || trim($slug) === '') {
            return null;
        }

        return route('catalog.show', ['slug' => $slug]);
    }

    private static function categoryHref(mixed $categoryId): ?string
    {
        $id = (int) $categoryId;
        if ($id <= 0) {
            return null;
        }

        return route('catalog.index', ['category' => $id]);
    }

    private static function localizedColumn(string $prefix, string $locale): string
    {
        return match ($locale) {
            'en' => $prefix.'_en',
            'ru' => $prefix.'_ru',
            default => $prefix.'_uk',
        };
    }

    private static function localizedValue(ShopHeaderBanner $row, string $column, string $fallbackColumn): string
    {
        $value = trim((string) ($row->{$column} ?? ''));
        if ($value !== '') {
            return $value;
        }

        return trim((string) ($row->{$fallbackColumn} ?? ''));
    }

    private static function urlHref(mixed $url): ?string
    {
        $href = trim((string) $url);
        if ($href === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        if (str_starts_with($href, '/')) {
            return url($href);
        }

        return url('/'.$href);
    }
}
