<?php

namespace App\Models;

use App\Support\SchemaCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Лічильники й підтвердження сайту для Google і Facebook/Instagram, які власник вводить в адмінці.
 * Порожнє поле — береться значення з .env (як було раніше).
 */
class ShopMarketingSetting extends Model
{
    public const GA4_PATTERN = '/^G-[A-Z0-9]+$/i';

    public const PIXEL_PATTERN = '/^\d{6,20}$/';

    /** Коди підтвердження — лише латиниця, цифри, «-» і «_» (вставляються в meta-тег). */
    public const VERIFICATION_PATTERN = '/^[A-Za-z0-9_-]{6,120}$/';

    private const MEMO_KEY = 'marketing-settings';

    protected $fillable = [
        'ga4_id',
        'meta_pixel_id',
        'google_site_verification',
        'facebook_domain_verification',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => StorefrontMemo::forget(self::MEMO_KEY));
        static::deleted(fn () => StorefrontMemo::forget(self::MEMO_KEY));
    }

    /**
     * @return array{ga4_id: ?string, meta_pixel_id: ?string, google_site_verification: ?string, facebook_domain_verification: ?string}
     */
    public static function resolved(): array
    {
        $row = StorefrontMemo::remember(self::MEMO_KEY, fn (): ?self => SchemaCache::hasTable('shop_marketing_settings')
            ? self::query()->orderBy('id')->first()
            : null);

        return [
            'ga4_id' => self::valid(strtoupper(trim((string) ($row?->ga4_id ?: config('shop.marketing.ga4_id')))), self::GA4_PATTERN),
            'meta_pixel_id' => self::valid($row?->meta_pixel_id ?: config('shop.marketing.meta_pixel_id'), self::PIXEL_PATTERN),
            'google_site_verification' => self::valid($row?->google_site_verification ?: config('shop.google_site_verification'), self::VERIFICATION_PATTERN),
            'facebook_domain_verification' => self::valid($row?->facebook_domain_verification, self::VERIFICATION_PATTERN),
        ];
    }

    private static function valid(mixed $value, string $pattern): ?string
    {
        $value = trim((string) $value);

        return preg_match($pattern, $value) === 1 ? $value : null;
    }
}
