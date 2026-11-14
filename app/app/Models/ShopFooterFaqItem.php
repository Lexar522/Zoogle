<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShopFooterFaqItem extends Model
{
    protected $fillable = [
        'sort_order',
        'question_uk',
        'question_en',
        'question_ru',
        'answer_uk',
        'answer_en',
        'answer_ru',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function questionForLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $raw = match ($locale) {
            'en' => $this->question_en ?: $this->question_uk ?: $this->question_ru,
            'ru' => $this->question_ru ?: $this->question_uk ?: $this->question_en,
            default => $this->question_uk ?: $this->question_en ?: $this->question_ru,
        };

        return trim((string) $raw);
    }

    public function answerForLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $raw = match ($locale) {
            'en' => $this->answer_en ?: $this->answer_uk ?: $this->answer_ru,
            'ru' => $this->answer_ru ?: $this->answer_uk ?: $this->answer_en,
            default => $this->answer_uk ?: $this->answer_en ?: $this->answer_ru,
        };

        return trim((string) $raw);
    }
}
