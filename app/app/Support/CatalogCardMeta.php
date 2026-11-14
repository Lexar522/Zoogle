<?php

namespace App\Support;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ShopCatalogGridSetting;

/**
 * Рядок під назвою картки: кольори або категорія — за налаштуванням «Сітка каталогу».
 * Рядок завжди однакової висоти, тож ціна й кошик стоять на одній лінії в усьому ряду.
 */
final class CatalogCardMeta
{
    /**
     * @return array{colors: list<array{name: string, hex: string, photo: ?string}>, text: ?string}|null null — рядка немає
     */
    public static function forListing(Product|Bundle $listing): ?array
    {
        $mode = ShopCatalogGridSetting::subtitleMode();
        if ($mode === ShopCatalogGridSetting::SUBTITLE_NONE) {
            return null;
        }

        if ($listing instanceof Bundle) {
            return ['colors' => [], 'text' => $mode === ShopCatalogGridSetting::SUBTITLE_COLORS ? null : self::bundleText($listing)];
        }

        $colors = $mode === ShopCatalogGridSetting::SUBTITLE_CATEGORY ? [] : CatalogCardColors::forProduct($listing);
        $wantsCategory = $mode === ShopCatalogGridSetting::SUBTITLE_CATEGORY
            || ($mode === ShopCatalogGridSetting::SUBTITLE_COLORS_OR_CATEGORY && $colors === []);

        return ['colors' => $colors, 'text' => $wantsCategory ? self::categoryName($listing) : null];
    }

    /**
     * «3 товари» — якщо склад комплекту вже завантажений для картки; інакше просто «Комплект».
     */
    private static function bundleText(Bundle $bundle): string
    {
        if (! $bundle->relationLoaded('items') || $bundle->items->isEmpty()) {
            return (string) __('shop.card_bundle_eyebrow');
        }

        return trans_choice('shop.card_bundle_items_count', $bundle->items->count());
    }

    private static function categoryName(Product $product): ?string
    {
        $valueId = (int) ($product->category_value_id ?: $product->category_parent_value_id);
        if ($valueId <= 0) {
            return null;
        }

        $value = StorefrontOptionCatalog::valuesIn(OptionGroup::systemCategoryGroupIdForCatalog(), [$valueId])->first();
        $name = $value ? mt($value->name) : '';

        return $name !== '' ? $name : null;
    }
}
