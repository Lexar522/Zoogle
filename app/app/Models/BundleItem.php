<?php

namespace App\Models;

use App\Services\ListingOptionSelectionService;
use App\Support\StoragePublicPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleItem extends Model
{
    protected $fillable = [
        'bundle_id',
        'product_id',
        'option_value_ids',
        'qty',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'option_value_ids' => 'array',
            'qty' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Фото позиції з урахуванням вибраних опцій: жовто-білий набір не має показувати фото іншого кольору.
     */
    public function displayPhotoPath(): ?string
    {
        $product = $this->product;
        if (! $product instanceof Product) {
            return null;
        }

        $optionValueIds = array_values(array_filter(array_map('intval', $this->option_value_ids ?? [])));
        $variant = $optionValueIds !== []
            ? app(ListingOptionSelectionService::class)->resolveVariantForLine($product, $optionValueIds)
            : null;

        return $this->firstImagePath($variant?->photos)
            ?? $this->firstImagePath($this->optionValuePhotos($product, $optionValueIds))
            ?? $this->firstImagePath($product->photos)
            ?? $this->firstImagePath($product->variants->flatMap(fn ($row): array => is_array($row->photos) ? $row->photos : [])->all());
    }

    /**
     * @param  list<int>  $optionValueIds
     * @return list<mixed>
     */
    private function optionValuePhotos(Product $product, array $optionValueIds): array
    {
        $photos = [];
        foreach ($product->variant_options ?? [] as $row) {
            foreach ($row['value_photos'] ?? [] as $photoRow) {
                if (in_array((int) ($photoRow['option_value_id'] ?? 0), $optionValueIds, true)) {
                    $photos = [...$photos, ...($photoRow['photos'] ?? [])];
                }
            }
        }

        return $photos;
    }

    private function firstImagePath(mixed $photos): ?string
    {
        foreach (StoragePublicPath::normalizeList($photos) as $path) {
            if (! preg_match('/\.(mp4|webm|mov|m4v)$/i', $path)) {
                return $path;
            }
        }

        return null;
    }
}
