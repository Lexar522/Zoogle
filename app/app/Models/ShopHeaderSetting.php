<?php

namespace App\Models;

use App\Support\ShopHeaderChipStyles;
use App\Support\StorefrontMemo;
use App\Support\StorefrontCategoryTreeCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopHeaderSetting extends Model
{
    protected static function booted(): void
    {
        static::saved(function (): void {
            StorefrontCategoryTreeCache::bump();
            StorefrontMemo::flush();
        });
    }

    /** Дефолтна макс. висота тіла розгорнутої категорії (px). */
    public const DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX = 320;

    /** Дефолтна висота слота банера (px). */
    public const DEFAULT_BANNER_HEIGHT_PX = 112;

    protected $fillable = [
        'featured_left_option_value_id',
        'featured_right_option_value_id',
        'featured_panel_max_height_px',
        'featured_left_panel_max_height_px',
        'featured_right_panel_max_height_px',
        'banner_height_px',
        'chip_styles',
    ];

    protected function casts(): array
    {
        return [
            'featured_panel_max_height_px' => 'integer',
            'featured_left_panel_max_height_px' => 'integer',
            'featured_right_panel_max_height_px' => 'integer',
            'banner_height_px' => 'integer',
            'chip_styles' => 'array',
        ];
    }

    public static function record(): self
    {
        return static::query()->firstOrCreate([], []);
    }

    /** Лише для читання на вітрині: шапка бере налаштування кілька разів за запит. */
    public static function storefront(): self
    {
        return StorefrontMemo::remember('shop-header-setting', fn (): self => self::record());
    }

    public function resolvedFeaturedPanelMaxHeightPx(): int
    {
        return $this->clampFeaturedPanelHeight((int) ($this->featured_panel_max_height_px ?? 0));
    }

    public function resolvedFeaturedLeftPanelMaxHeightPx(): int
    {
        $value = (int) ($this->featured_left_panel_max_height_px ?? 0);
        if ($value > 0) {
            return $this->clampFeaturedPanelHeight($value);
        }

        return $this->resolvedFeaturedPanelMaxHeightPx();
    }

    public function resolvedFeaturedRightPanelMaxHeightPx(): int
    {
        $value = (int) ($this->featured_right_panel_max_height_px ?? 0);
        if ($value > 0) {
            return $this->clampFeaturedPanelHeight($value);
        }

        return $this->resolvedFeaturedPanelMaxHeightPx();
    }

    public function resolvedBannerHeightPx(): int
    {
        $value = (int) ($this->banner_height_px ?? 0);

        return $value > 0 ? min(800, max(64, $value)) : self::DEFAULT_BANNER_HEIGHT_PX;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedChipStylesForForm(): array
    {
        return ShopHeaderChipStyles::mergeForForm(
            is_array($this->chip_styles) ? $this->chip_styles : null
        );
    }

    public function chipStylesInlineCss(): string
    {
        return ShopHeaderChipStyles::toInlineCss(
            is_array($this->chip_styles) ? $this->chip_styles : null
        );
    }

    private function clampFeaturedPanelHeight(int $value): int
    {
        return $value > 0
            ? min(1200, max(80, $value))
            : self::DEFAULT_FEATURED_PANEL_MAX_HEIGHT_PX;
    }

    /** @return BelongsTo<OptionValue, $this> */
    public function featuredLeft(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class, 'featured_left_option_value_id');
    }

    /** @return BelongsTo<OptionValue, $this> */
    public function featuredRight(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class, 'featured_right_option_value_id');
    }
}
