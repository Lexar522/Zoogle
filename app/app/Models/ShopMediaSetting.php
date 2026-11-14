<?php

namespace App\Models;

use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Model;

class ShopMediaSetting extends Model
{
    protected $fillable = [
        'optimize_images',
    ];

    protected function casts(): array
    {
        return [
            'optimize_images' => 'boolean',
        ];
    }

    private static ?bool $optimizeImages = null;

    /**
     * Увімкнено зменшені копії та покрокову підгрузку карток.
     * Поки запису немає — оптимізація працює.
     */
    public static function optimizeImagesEnabled(): bool
    {
        if (self::$optimizeImages !== null) {
            return self::$optimizeImages;
        }

        if (! SchemaCache::hasTable('shop_media_settings')) {
            return self::$optimizeImages = true;
        }

        $row = self::query()->orderBy('id')->first();

        return self::$optimizeImages = $row === null ? true : (bool) $row->optimize_images;
    }

    public static function forgetOptimizeImagesCache(): void
    {
        self::$optimizeImages = null;
    }
}
