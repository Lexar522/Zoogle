<?php

namespace App\Models;

use App\Support\CatalogProductsTable;
use App\Support\ListingMedia;
use App\Support\ListingPhotoDerivative;
use App\Support\RichTextSanitizer;
use App\Support\SchemaCache;
use App\Support\StoragePublicPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    public const STOCK_IN_STOCK = 'in_stock';

    public const STOCK_OUT_OF_STOCK = 'out_of_stock';

    public const STOCK_PREORDER = 'preorder';

    public function getTable(): string
    {
        return CatalogProductsTable::name();
    }

    protected $fillable = [
        'stock_quantity',
        'title',
        'slug',
        'sku',
        'product_type',
        'category_parent_value_id',
        'category_value_id',
        'description',
        'short_description',
        'dimensions',
        'length_cm',
        'width_cm',
        'height_cm',
        'price',
        'is_available',
        'stock_status',
        'photos',
        'photo_meta',
        'variant_options',
        'published_at',
        'exclude_from_merchant_feed',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->isDirty('stock_quantity') && $product->stock_quantity !== null) {
                $status = $product->stockStatus();
                if ((int) $product->stock_quantity <= 0 && $status === self::STOCK_IN_STOCK) {
                    $product->stock_status = self::STOCK_OUT_OF_STOCK;
                } elseif ((int) $product->stock_quantity > 0 && $status === self::STOCK_OUT_OF_STOCK) {
                    $product->stock_status = self::STOCK_IN_STOCK;
                }
            }

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
        'stock_quantity' => 'integer',
        'sku' => 'string',
        'product_type' => 'string',
        'category_parent_value_id' => 'integer',
        'category_value_id' => 'integer',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'stock_status' => 'string',
        'photos' => 'array',
        'photo_meta' => 'array',
        'variant_options' => 'array',
        'published_at' => 'datetime',
        'exclude_from_merchant_feed' => 'boolean',
    ];

    public function orderItems(): HasMany
    {
        $fk = SchemaCache::hasColumn((new OrderItem)->getTable(), 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->hasMany(OrderItem::class, $fk);
    }

    public function variants(): HasMany
    {
        $variantTable = (new ProductVariant)->getTable();
        $fk = SchemaCache::hasColumn($variantTable, 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->hasMany(ProductVariant::class, $fk);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function answeredQuestions(): HasMany
    {
        return $this->questions()->answered()->latest('answered_at')->latest('id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->approved()->latest('approved_at')->latest('id');
    }

    public function careArticles(): HasMany
    {
        return $this->hasMany(ProductCareArticle::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Рекомендовані («Схожі») товари для PDP — ручний список з адмінки.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function recommendedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'product_recommendations',
            'product_id',
            'recommended_product_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderByPivot('id');
    }

    /**
     * Категорії каталогу (основна + додаткові), у яких товар показується у видачі.
     *
     * @return BelongsToMany<OptionValue, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            OptionValue::class,
            'product_categories',
            'product_id',
            'category_value_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderByPivot('id');
    }

    /**
     * @return array<string, string>
     */
    public static function stockStatusOptions(): array
    {
        return [
            self::STOCK_IN_STOCK => 'В наявності',
            self::STOCK_OUT_OF_STOCK => 'Немає в наявності',
            self::STOCK_PREORDER => 'Під замовлення',
        ];
    }

    public function stockStatus(): string
    {
        $status = (string) ($this->stock_status ?? self::STOCK_IN_STOCK);

        return array_key_exists($status, self::stockStatusOptions())
            ? $status
            : self::STOCK_IN_STOCK;
    }

    public function isInStock(): bool
    {
        return $this->stockStatus() === self::STOCK_IN_STOCK;
    }

    public function isOutOfStock(): bool
    {
        return $this->stockStatus() === self::STOCK_OUT_OF_STOCK;
    }

    public function allowsPreorder(): bool
    {
        return $this->stockStatus() === self::STOCK_PREORDER;
    }

    public function isOrderable(): bool
    {
        if ($this->allowsPreorder()) {
            return true;
        }

        if ($this->isOutOfStock()) {
            return false;
        }

        if ($this->relationLoaded('variants')) {
            $visible = $this->variants->filter(
                fn (ProductVariant $variant): bool => $variant->isVisibleOnStorefront()
            );
            if ($visible->isNotEmpty()) {
                return $visible->contains(
                    fn (ProductVariant $variant): bool => $variant->isSellable() || $variant->allows_preorder
                );
            }
        }

        return $this->isInStock();
    }

    public function defersOnlinePayment(): bool
    {
        return $this->allowsPreorder();
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
                && ! ListingPhotoDerivative::isDerivativePath($path)
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
            ->filter(fn (string $path): bool => (bool) preg_match('/\.(jpe?g|png|webp|gif|mp4|webm|mov)$/i', $path))
            ->reject(fn (string $path): bool => ListingPhotoDerivative::isDerivativePath($path))
            ->sort()
            ->values()
            ->all();
    }

    public function firstCatalogPhotoPath(): ?string
    {
        $item = $this->firstCatalogGalleryItem();

        return is_array($item) ? ($item['path'] ?? null) : null;
    }

    /**
     * Перший шлях фото з уже завантажених даних, без перевірки, чи файл є на диску.
     * Для списків адмінки: перевірка кожного файлу на хостингу робить сторінку повільною.
     */
    public function firstListedPhotoPath(): ?string
    {
        foreach (StoragePublicPath::normalizeList($this->photos) as $path) {
            if (! ListingPhotoDerivative::isDerivativePath($path)) {
                return $path;
            }
        }

        $variants = $this->relationLoaded('variants') ? $this->variants : collect();
        foreach ($variants as $variant) {
            foreach (StoragePublicPath::normalizeList($variant->photos ?? null) as $path) {
                if (! ListingPhotoDerivative::isDerivativePath($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * @return array{
     *     path: string,
     *     url: string,
     *     type: string,
     *     focus_x: float,
     *     focus_y: float,
     *     poster?: string,
     *     poster_url?: string,
     *     object_position: string
     * }|null
     */
    public function firstCatalogGalleryItem(): ?array
    {
        $items = $this->storefrontGalleryItems();

        return ListingMedia::firstCatalogPreviewItem($items);
    }

    /**
     * @return list<array{
     *     path: string,
     *     url: string,
     *     type: string,
     *     focus_x: float,
     *     focus_y: float,
     *     poster?: string,
     *     poster_url?: string,
     *     object_position: string
     * }>
     */
    public function storefrontGalleryItems(): array
    {
        return ListingMedia::storefrontItemsForPaths(
            $this->storefrontGalleryStoragePaths(),
            ListingMedia::normalizeMetaMap($this->photo_meta ?? [])
        );
    }

    /**
     * @return list<string>
     */
    public function resolvedPhotoMetaPaths(): array
    {
        return array_keys(ListingMedia::normalizeMetaMap($this->photo_meta ?? []));
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
