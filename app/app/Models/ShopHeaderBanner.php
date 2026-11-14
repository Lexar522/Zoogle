<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopHeaderBanner extends Model
{
    public const LINK_NONE = 'none';

    public const LINK_PRODUCT = 'product';

    public const LINK_URL = 'url';

    public const LINK_CATEGORY = 'category';

    public const LINK_ON_SALE = 'on_sale';

    public const SLOT_TOP = 'top';

    public const SLOT_BOTTOM = 'bottom';

    protected $fillable = [
        'image_path',
        'link_type',
        'product_id',
        'url',
        'category_option_value_id',
        'sort_order',
        'slot',
        'is_active',
        'text_uk',
        'text_en',
        'text_ru',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<OptionValue, $this> */
    public function categoryOptionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class, 'category_option_value_id');
    }

    /**
     * @return array<string, string>
     */
    public static function linkTypeLabels(): array
    {
        return [
            self::LINK_NONE => 'Без посилання',
            self::LINK_PRODUCT => 'Товар',
            self::LINK_URL => 'URL',
            self::LINK_CATEGORY => 'Категорія каталогу',
            self::LINK_ON_SALE => 'Акції',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function slotLabels(): array
    {
        return [
            self::SLOT_TOP => 'Верхній банер',
            self::SLOT_BOTTOM => 'Нижній банер',
        ];
    }
}
