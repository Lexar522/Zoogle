<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopInfoPage extends Model
{
    protected $table = 'shop_info_pages';

    protected $fillable = [
        'sort_order',
        'slug',
        'title_uk',
        'title_en',
        'title_ru',
        'body_uk',
        'body_en',
        'body_ru',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function titleForLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $raw = match ($locale) {
            'en' => $this->title_en ?: $this->title_uk ?: $this->title_ru,
            'ru' => $this->title_ru ?: $this->title_uk ?: $this->title_en,
            default => $this->title_uk ?: $this->title_en ?: $this->title_ru,
        };

        return trim((string) $raw);
    }

    public function bodyForLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $raw = match ($locale) {
            'en' => $this->body_en ?: $this->body_uk ?: $this->body_ru,
            'ru' => $this->body_ru ?: $this->body_uk ?: $this->body_en,
            default => $this->body_uk ?: $this->body_en ?: $this->body_ru,
        };

        return trim((string) $raw);
    }
}
