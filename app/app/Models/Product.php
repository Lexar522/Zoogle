<?php

namespace App\Models;

use App\Support\CatalogProductsTable;
use App\Support\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    use HasFactory;

    public function getTable(): string
    {
        return CatalogProductsTable::name();
    }

    protected $fillable = [
        'title',
        'slug',
        'sku',
        'product_type',
        'category_parent_value_id',
        'category_value_id',
        'description',
        'short_description',
        'dimensions',
        'price',
        'is_available',
        'search_tags',
        'photos',
        'variant_options',
        'published_at',
    ];

    protected $casts = [
        'sku' => 'string',
        'product_type' => 'string',
        'category_parent_value_id' => 'integer',
        'category_value_id' => 'integer',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'search_tags' => 'array',
        'photos' => 'array',
        'variant_options' => 'array',
        'published_at' => 'datetime',
    ];

    public function orderItems(): HasMany
    {
        $fk = Schema::hasColumn((new OrderItem)->getTable(), 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->hasMany(OrderItem::class, $fk);
    }

    public function variants(): HasMany
    {
        $variantTable = (new ProductVariant)->getTable();
        $fk = Schema::hasColumn($variantTable, 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->hasMany(ProductVariant::class, $fk);
    }

    public function careArticles(): HasMany
    {
        return $this->hasMany(ProductCareArticle::class)->orderBy('sort_order')->orderBy('id');
    }

    public function publishedCareArticles(): HasMany
    {
        return $this->careArticles()->published();
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favorites')->withTimestamps();
    }

    public function firstCatalogPhotoPath(): ?string
    {
        foreach ($this->normalizePhotoList($this->photos) as $path) {
            return $path;
        }

        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        foreach ($variants as $variant) {
            foreach ($this->normalizePhotoList($variant->photos ?? null) as $path) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Шляхи файлів у storage для галереї на PDP і картці каталогу (до вибору опцій кольору тощо).
     * Об’єднує фото картки товару та унікальні фото варіантів (якщо кілька знімків лише на SKU).
     *
     * @return list<string>
     */
    public function storefrontGalleryStoragePaths(): array
    {
        $seen = [];
        $out = [];

        foreach ($this->normalizePhotoList($this->photos ?? null) as $path) {
            if (! isset($seen[$path])) {
                $seen[$path] = true;
                $out[] = $path;
            }
        }

        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();

        foreach ($variants as $variant) {
            foreach ($this->normalizePhotoList($variant->photos ?? null) as $path) {
                if (! isset($seen[$path])) {
                    $seen[$path] = true;
                    $out[] = $path;
                }
            }
        }

        return $out;
    }

    /**
     * Повні URL тієї ж галереї, що initialPhotos на сторінці товару.
     *
     * @return list<string>
     */
    public function storefrontGalleryAssetUrls(): array
    {
        return array_map(
            fn (string $path): string => asset('storage/'.$path),
            $this->storefrontGalleryStoragePaths()
        );
    }

    public function safeShortDescriptionHtml(): string
    {
        return RichTextSanitizer::toHtml($this->short_description);
    }

    public function safeDescriptionHtml(): string
    {
        return RichTextSanitizer::toHtml($this->description);
    }

    public function hasRichDescriptionSection(): bool
    {
        return $this->hasDisplayableShortDescription() || $this->hasDisplayableDescription();
    }

    public function hasDisplayableShortDescription(): bool
    {
        return RichTextSanitizer::hasVisibleContent($this->short_description);
    }

    public function hasDisplayableDescription(): bool
    {
        return RichTextSanitizer::hasVisibleContent($this->description);
    }

    /**
     * @return list<string>
     */
    private function normalizePhotoList(mixed $photos): array
    {
        if (! is_array($photos)) {
            return [];
        }

        $out = [];
        foreach ($photos as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $out[] = ltrim($path, '/');
        }

        return $out;
    }
}
