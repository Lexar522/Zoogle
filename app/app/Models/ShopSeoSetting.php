<?php

namespace App\Models;

use App\Support\SchemaCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Заголовок і опис головної сторінки для Google (те, що видно під посиланням у пошуку).
 */
class ShopSeoSetting extends Model
{
    public const TITLE_MAX = 120;

    public const DESCRIPTION_MAX = 300;

    private const MEMO_KEY = 'seo-settings';

    protected $fillable = [
        'home_title',
        'home_description',
        'home_og_title',
        'home_og_description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetResolvedCache());
        static::deleted(fn () => self::forgetResolvedCache());
    }

    /**
     * Тексти головної: власні з адмінки (українською) або типові з перекладів.
     * Картка посилання в месенджерах бере свій текст, а без нього — текст для Google.
     *
     * @return array{title: string, description: string, og_title: string, og_description: string}
     */
    public static function home(): array
    {
        $row = StorefrontMemo::remember(self::MEMO_KEY, fn (): ?self => SchemaCache::hasTable('shop_seo_settings')
            ? self::query()->orderBy('id')->first()
            : null);

        $own = fn (string $column): string => app()->getLocale() === 'uk' ? trim((string) $row?->getAttribute($column)) : '';
        $title = $own('home_title');
        $description = $own('home_description');
        $ogTitle = $own('home_og_title') ?: $title;
        $ogDescription = $own('home_og_description') ?: $description;

        return [
            'title' => $title !== '' ? $title : __('shop.home_page_title'),
            'description' => $description !== '' ? $description : __('shop.home_meta_description'),
            'og_title' => $ogTitle !== '' ? $ogTitle : __('shop.home_og_title'),
            'og_description' => $ogDescription !== '' ? $ogDescription : __('shop.home_og_description'),
        ];
    }

    public static function forgetResolvedCache(): void
    {
        StorefrontMemo::forget(self::MEMO_KEY);
    }
}
