<?php

namespace App\Models;

use App\Support\SchemaCache;
use App\Support\StorefrontMemo;
use Illuminate\Database\Eloquent\Model;

/**
 * Вигляд сітки каталогу з адмінки: колонки, рядок під назвою, рядки назви, форма фото.
 */
class ShopCatalogGridSetting extends Model
{
    public const SUBTITLE_COLORS_OR_CATEGORY = 'colors_or_category';

    public const SUBTITLE_COLORS = 'colors';

    public const SUBTITLE_CATEGORY = 'category';

    public const SUBTITLE_NONE = 'none';

    /** @var array<string, string> */
    public const SUBTITLE_MODES = [
        self::SUBTITLE_COLORS_OR_CATEGORY => 'Кольори, а якщо їх немає — категорія',
        self::SUBTITLE_COLORS => 'Лише кольори',
        self::SUBTITLE_CATEGORY => 'Лише категорія',
        self::SUBTITLE_NONE => 'Нічого',
    ];

    /** @var array<string, string> значення → CSS aspect-ratio */
    public const PHOTO_RATIOS = [
        '1/1' => '1 / 1',
        '4/5' => '4 / 5',
        '4/3' => '4 / 3',
    ];

    public const DEFAULTS = [
        'columns_desktop' => 4,
        'columns_tablet' => 2,
        'columns_mobile' => 2,
        'subtitle_mode' => self::SUBTITLE_COLORS_OR_CATEGORY,
        'title_lines' => 2,
        'photo_ratio' => '1/1',
    ];

    /** @var array<string, array{0: int, 1: int}> */
    private const COLUMN_LIMITS = [
        'columns_desktop' => [3, 5],
        'columns_tablet' => [2, 4],
        'columns_mobile' => [1, 2],
    ];

    protected $fillable = [
        'columns_desktop',
        'columns_tablet',
        'columns_mobile',
        'subtitle_mode',
        'title_lines',
        'photo_ratio',
    ];

    protected function casts(): array
    {
        return [
            'columns_desktop' => 'integer',
            'columns_tablet' => 'integer',
            'columns_mobile' => 'integer',
            'title_lines' => 'integer',
        ];
    }

    private const MEMO_KEY = 'catalog-grid-settings';

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetResolvedCache());
        static::deleted(fn () => self::forgetResolvedCache());
    }

    /**
     * @return array{columns_desktop: int, columns_tablet: int, columns_mobile: int, subtitle_mode: string, title_lines: int, photo_ratio: string}
     */
    public static function resolved(): array
    {
        return StorefrontMemo::remember(
            self::MEMO_KEY,
            fn (): array => self::clamp(self::stored()?->only(array_keys(self::DEFAULTS)) ?? [])
        );
    }

    /**
     * Приводить будь-які значення до допустимих (з бази чи з форми адмінки).
     *
     * @param  array<string, mixed>  $values
     * @return array{columns_desktop: int, columns_tablet: int, columns_mobile: int, subtitle_mode: string, title_lines: int, photo_ratio: string}
     */
    public static function clamp(array $values): array
    {
        $out = [];
        foreach (self::COLUMN_LIMITS as $key => [$min, $max]) {
            $value = (int) ($values[$key] ?? self::DEFAULTS[$key]);
            $out[$key] = $value < $min ? self::DEFAULTS[$key] : min($max, $value);
        }

        $mode = (string) ($values['subtitle_mode'] ?? '');
        $out['subtitle_mode'] = isset(self::SUBTITLE_MODES[$mode]) ? $mode : self::DEFAULTS['subtitle_mode'];
        $out['title_lines'] = max(1, min(3, (int) ($values['title_lines'] ?? self::DEFAULTS['title_lines'])));
        $ratio = (string) ($values['photo_ratio'] ?? '');
        $out['photo_ratio'] = isset(self::PHOTO_RATIOS[$ratio]) ? $ratio : self::DEFAULTS['photo_ratio'];

        return $out;
    }

    /**
     * CSS-змінні для сітки каталогу. Між планшетом і великим екраном — на колонку менше за десктоп.
     */
    public static function gridStyle(): string
    {
        $s = self::resolved();
        $medium = max($s['columns_tablet'], $s['columns_desktop'] - 1);

        return '--grid-cols-desktop: '.$s['columns_desktop'].'; '
            .'--grid-cols-medium: '.$medium.'; '
            .'--grid-cols-tablet: '.$s['columns_tablet'].'; '
            .'--grid-cols-mobile: '.$s['columns_mobile'].'; '
            .'--product-card-title-lines: '.$s['title_lines'].'; '
            .'--product-card-photo-ratio: '.self::PHOTO_RATIOS[$s['photo_ratio']].';';
    }

    public static function subtitleMode(): string
    {
        return self::resolved()['subtitle_mode'];
    }

    public static function forgetResolvedCache(): void
    {
        StorefrontMemo::forget(self::MEMO_KEY);
    }

    private static function stored(): ?self
    {
        if (! SchemaCache::hasTable('shop_catalog_grid_settings')) {
            return null;
        }

        return self::query()->orderBy('id')->first();
    }
}
