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
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetResolvedCache());
        static::deleted(fn () => self::forgetResolvedCache());
    }

    /**
     * Тексти головної: власні з адмінки (українською) або типові з перекладів.
     *
     * @return array{title: string, description: string, og_title: string, og_description: string}
     */
    public static function home(): array
    {
        $row = StorefrontMemo::remember(self::MEMO_KEY, fn (): ?self => SchemaCache::hasTable('shop_seo_settings')
            ? self::query()->orderBy('id')->first()
            : null);

        $useOwn = app()->getLocale() === 'uk';
        $title = $useOwn ? trim((string) $row?->home_title) : '';
        $description = $useOwn ? trim((string) $row?->home_description) : '';

        return [
            'title' => $title !== '' ? $title : __('shop.home_page_title'),
            'description' => $description !== '' ? $description : __('shop.home_meta_description'),
            'og_title' => $title !== '' ? $title : __('shop.home_og_title'),
            'og_description' => $description !== '' ? $description : __('shop.home_og_description'),
        ];
    }

    public static function forgetResolvedCache(): void
    {
        StorefrontMemo::forget(self::MEMO_KEY);
    }
}
