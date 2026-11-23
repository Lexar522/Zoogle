<?php

namespace App\Models;

use App\Support\ListingMedia;
use App\Support\RichTextSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Тварина у «Віртуальній опіці»: історія, фото й ціни за термін опікунства.
 */
class SponsorAnimal extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'species',
        'excerpt',
        'story',
        'photos',
        'photo_meta',
        'price_month',
        'price_half_year',
        'price_year',
        'jar_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'photo_meta' => 'array',
            'price_month' => 'decimal:2',
            'price_half_year' => 'decimal:2',
            'price_year' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $animal): void {
            $slug = Str::slug((string) ($animal->slug ?: $animal->name));
            $animal->slug = $slug !== '' ? $slug : 'animal-'.Str::lower(Str::random(6));
        });
    }

    /** @return HasMany<Sponsorship, $this> */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Ціна за термін або null, якщо цей термін для тварини не пропонується.
     */
    public function priceForTerm(string $term): ?float
    {
        $column = Sponsorship::TERMS[$term]['price_column'] ?? null;
        if ($column === null) {
            return null;
        }

        $price = $this->{$column};

        return $price !== null && (float) $price > 0 ? round((float) $price, 2) : null;
    }

    /**
     * @return array<string, float> термін → ціна, лише ті, що пропонуються
     */
    public function offeredTerms(): array
    {
        $out = [];
        foreach (array_keys(Sponsorship::TERMS) as $term) {
            $price = $this->priceForTerm($term);
            if ($price !== null) {
                $out[$term] = $price;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function galleryItems(): array
    {
        $paths = array_values(array_filter(is_array($this->photos) ? $this->photos : [], 'is_string'));

        return ListingMedia::storefrontItemsForPaths($paths, ListingMedia::normalizeMetaMap($this->photo_meta ?? []));
    }

    public function coverImageUrl(): ?string
    {
        foreach ($this->galleryItems() as $item) {
            if (($item['type'] ?? 'image') === 'image') {
                return (string) ($item['card_url'] ?? $item['url']);
            }
        }

        return null;
    }

    public function storyHtml(): string
    {
        return RichTextSanitizer::toCareArticleHtml($this->story);
    }

    /**
     * Банка тварини, а якщо її немає — загальна банка розділу.
     */
    public function resolvedJarUrl(): ?string
    {
        $own = trim((string) $this->jar_url);
        if (ShopCharitySetting::isAllowedJarUrl($own)) {
            return $own;
        }

        return ShopSponsorshipSetting::resolved()['jar_url'];
    }
}
