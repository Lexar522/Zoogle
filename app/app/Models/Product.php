<?php

namespace App\Models;

use App\Support\CatalogProductsTable;
use App\Support\RichTextSanitizer;
use App\Support\StoragePublicPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (! $product->isDirty('photos')) {
                return;
            }

            $disk = Storage::disk('public');
            $photos = StoragePublicPath::normalizeList($product->photos);

            $product->photos = array_values(array_filter(
                $photos,
                fn (string $path): bool => ! str_starts_with($path, 'livewire-tmp/')
                    && $disk->exists($path)
            ));
        });
    }

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

    /**
     * Шляхи з БД, що реально існують на диску; якщо в БД порожньо — з каталогу products/{id}/photos.
     *
     * @return list<string>
     */
    public function resolvedListingPhotoPaths(): array
    {
        $disk = Storage::disk('public');
        $fromDb = array_values(array_filter(
            $this->normalizePhotoList($this->photos),
            fn (string $path): bool => $disk->exists($path)
        ));

        if ($fromDb !== []) {
            return $fromDb;
        }

        return $this->photosPathsFromDisk();
    }

    /**
     * @return list<string>
     */
    public function photosPathsFromDisk(): array
    {
        $id = (int) $this->id;
        if ($id <= 0) {
            return [];
        }

        $directory = 'products/'.$id.'/photos';
        if (! Storage::disk('public')->exists($directory)) {
            return [];
        }

        return collect(Storage::disk('public')->files($directory))
            ->filter(fn (string $path): bool => (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', $path))
            ->sort()
            ->values()
            ->all();
    }

    public function firstCatalogPhotoPath(): ?string
    {
        foreach ($this->resolvedListingPhotoPaths() as $path) {
            return $path;
        }

        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();

        foreach ($variants as $variant) {
            foreach ($this->normalizePhotoList($variant->photos ?? null) as $path) {
                if (Storage::disk('public')->exists($path)) {
                    return $path;
                }
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

        foreach ($this->resolvedListingPhotoPaths() as $path) {
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
     * Вузол дерева категорії для правил чекауту: лист, корінь (якщо товар лише в кореневій рубриці), або значення з variant_options.
     */
    public function resolvedCatalogCategoryNodeId(?int $categoryGroupId = null): int
    {
        $cv = (int) ($this->category_value_id ?? 0);
        if ($cv > 0) {
            return $cv;
        }

        $parent = (int) ($this->category_parent_value_id ?? 0);
        if ($parent > 0) {
            return $parent;
        }

        $groupId = $categoryGroupId ?? OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0) {
            return 0;
        }

        $rows = is_array($this->variant_options) ? $this->variant_options : [];
        foreach ($rows as $row) {
            if ((int) ($row['option_group_id'] ?? 0) !== $groupId) {
                continue;
            }

            $first = collect($row['option_value_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->first();

            return $first ?? 0;
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function normalizePhotoList(mixed $photos): array
    {
        return StoragePublicPath::normalizeList($photos);
    }
}
