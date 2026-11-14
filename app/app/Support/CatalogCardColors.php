<?php

namespace App\Support;

use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ShopMediaSetting;

/**
 * Кольори товару для кружечків на картці каталогу. Групи й значення — з пам'яті запиту
 * (StorefrontOptionCatalog), тож сітка карток не робить запиту на кожну картку.
 */
final class CatalogCardColors
{
    /**
     * @return list<array{name: string, hex: string, photo: ?string}>
     */
    public static function forProduct(Product $product): array
    {
        $colors = [];
        foreach (is_array($product->variant_options) ? $product->variant_options : [] as $row) {
            $groupId = (int) ($row['option_group_id'] ?? 0);
            $group = StorefrontOptionCatalog::group($groupId);
            if ($group === null || ! $group->is_active || $group->value_type !== 'color') {
                continue;
            }

            $valueIds = array_map('intval', is_array($row['option_value_ids'] ?? null) ? $row['option_value_ids'] : []);
            $photoPaths = self::firstPhotoPathByValue($row);
            foreach (StorefrontOptionCatalog::valuesIn($groupId, $valueIds, activeOnly: true) as $value) {
                $hex = self::safeHex($value);
                if ($hex !== null && ! isset($colors[$hex])) {
                    $colors[$hex] = [
                        'name' => mt($value->name),
                        'hex' => $hex,
                        'photo' => self::cardPhotoUrl($photoPaths[(int) $value->id] ?? null),
                    ];
                }
            }
        }

        return array_values($colors);
    }

    /**
     * Перше фото, прив'язане до кожного кольору в адмінці (value_photos рядка опції).
     *
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private static function firstPhotoPathByValue(array $row): array
    {
        $paths = [];
        foreach (is_array($row['value_photos'] ?? null) ? $row['value_photos'] : [] as $photoRow) {
            $valueId = (int) ($photoRow['option_value_id'] ?? 0);
            foreach (is_array($photoRow['photos'] ?? null) ? $photoRow['photos'] : [] as $raw) {
                $path = StoragePublicPath::normalizeFileUploadState($raw)
                    ?? (is_string($raw) ? StoragePublicPath::normalize($raw) : null);
                if ($valueId > 0 && is_string($path) && $path !== '' && ! isset($paths[$valueId])) {
                    $paths[$valueId] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * Той самий URL, що й у фото картки (product-gallery-media): мініатюра, якщо оптимізація ввімкнена.
     */
    private static function cardPhotoUrl(?string $path): ?string
    {
        if ($path === null || ListingMedia::typeForPath($path) === ListingMedia::TYPE_VIDEO) {
            return null;
        }

        $item = ListingMedia::storefrontItemForPath($path, []);
        $url = (string) $item['url'];
        if (ShopMediaSetting::optimizeImagesEnabled() && (string) ($item['card_url'] ?? '') !== '') {
            $url = (string) $item['card_url'];
        }

        return $url !== '' ? $url : null;
    }

    /**
     * Колір іде в атрибут style, тож пропускаємо лише #rgb / #rrggbb.
     */
    private static function safeHex(OptionValue $value): ?string
    {
        $hex = strtolower(trim((string) $value->color_hex));

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $hex) === 1 ? $hex : null;
    }
}
