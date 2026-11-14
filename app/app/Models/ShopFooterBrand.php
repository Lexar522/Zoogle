<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopFooterBrand extends Model
{
    protected $fillable = [
        'body',
        'phone',
        'site_title',
        'logo_path',
        'faq_title_uk',
        'faq_title_en',
        'faq_title_ru',
    ];

    public static function record(): self
    {
        $row = self::query()->first();
        if ($row !== null) {
            return $row;
        }

        /** @var self $created */
        $created = self::query()->create([
            'body' => null,
            'phone' => null,
            'site_title' => null,
            'logo_path' => null,
            'faq_title_uk' => null,
            'faq_title_en' => null,
            'faq_title_ru' => null,
        ]);

        return $created;
    }
}
