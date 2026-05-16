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
        ]);

        return $created;
    }
}
