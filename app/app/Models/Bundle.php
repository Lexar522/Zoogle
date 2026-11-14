<?php

namespace App\Models;

use App\Support\StoragePublicPath;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'sku',
        'description',
        'short_description',
        'photos',
        'show_item_photos_in_gallery',
        'variant_options',
        'category_parent_value_id',
        'category_value_id',
        'is_visible',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'show_item_photos_in_gallery' => 'boolean',
            'variant_options' => 'array',
            'is_visible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('sort_order');
    }

    public function firstCatalogPhotoPath(): ?string
    {
        return $this->galleryPhotoPaths()[0] ?? null;
    }

    /**
     * Власні фото комплекту. Якщо їх немає: з увімкненим show_item_photos_in_gallery
     * беремо фото всіх позицій у вибраних опціях, інакше лише одне фото першої позиції,
     * щоб галерея не була порожньою, але не розкривала весь склад.
     *
     * @return list<string>
     */
    public function galleryPhotoPaths(): array
    {
        $own = $this->normalizePhotoList($this->photos);
        if ($own !== []) {
            return $own;
        }

        $itemPhotos = $this->itemPhotoPaths();

        return $this->show_item_photos_in_gallery ? $itemPhotos : array_slice($itemPhotos, 0, 1);
    }

    /**
     * @return list<string>
     */
    private function itemPhotoPaths(): array
    {
        /** @var EloquentCollection<int, BundleItem> $items */
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with(['product.variants'])->get();

        return $items
            ->map(fn (BundleItem $item): ?string => $item->displayPhotoPath())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizePhotoList(mixed $photos): array
    {
        return StoragePublicPath::normalizeList($photos);
    }
}
