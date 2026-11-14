<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ShopContactItem;
use App\Models\ShopFooterBrand;
use App\Models\ShopFooterColumn;
use App\Models\ShopFooterFaqItem;
use App\Models\ShopFooterLink;
use App\Models\ShopHeaderBanner;
use App\Models\ShopHeaderSetting;
use App\Models\ShopHomeBenefit;
use App\Models\ShopHomeProductSection;
use App\Models\ShopInfoPage;
use App\Models\ShopIntegrationSetting;
use App\Models\ShopMediaSetting;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Готові масиви шапки, футера й блоків головної, які однакові для всіх відвідувачів.
 * Скидаються, коли будь-яку з моделей нижче зберігають або видаляють в адмінці;
 * короткий TTL страхує зміни в обхід подій моделей (нові копії фото, масове сортування).
 */
final class StorefrontViewCache
{
    private const VERSION_KEY = 'storefront.view-cache-version';

    private const TTL_SECONDS = 600;

    /** @var list<class-string<\Illuminate\Database\Eloquent\Model>> */
    public const SOURCE_MODELS = [
        Product::class,
        ShopContactItem::class,
        ShopFooterBrand::class,
        ShopFooterColumn::class,
        ShopFooterFaqItem::class,
        ShopFooterLink::class,
        ShopHeaderBanner::class,
        ShopHeaderSetting::class,
        ShopHomeBenefit::class,
        ShopHomeProductSection::class,
        ShopInfoPage::class,
        ShopIntegrationSetting::class,
        ShopMediaSetting::class,
    ];

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function remember(string $name, Closure $build): mixed
    {
        $version = StorefrontMemo::remember('view-cache-version', fn (): int => (int) Cache::get(self::VERSION_KEY, 1));
        // URL сайту теж у ключі: посилання в масивах абсолютні.
        $key = 'storefront.view.'.$version.'.'.$name.'.'.app()->getLocale().'.'.md5(url('/'));

        return StorefrontMemo::remember($key, fn () => Cache::remember($key, self::TTL_SECONDS, $build));
    }

    public static function bump(): void
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        Cache::forever(self::VERSION_KEY, $version + 1);
        StorefrontMemo::flush();
    }

    public static function registerInvalidation(): void
    {
        foreach (self::SOURCE_MODELS as $model) {
            $model::saved(fn () => self::bump());
            $model::deleted(fn () => self::bump());
        }
    }
}
