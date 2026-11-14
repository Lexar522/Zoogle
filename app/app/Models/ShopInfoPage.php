<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ShopInfoPage extends Model
{
    protected $table = 'shop_info_pages';

    protected $fillable = [
        'parent_id',
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
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function hasChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this->children()->exists();
    }

    /**
     * @return Collection<int, self>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current !== null) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
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
