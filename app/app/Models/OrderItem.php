<?php

namespace App\Models;

use App\Support\ListingPhotoDerivative;
use App\Support\OptionLabel;
use App\Support\SchemaCache;
use App\Support\StoragePublicPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'bundle_id',
        'title_snapshot',
        'option_value_ids',
        'bundle_snapshot',
        'price',
        'qty',
        'line_total',
        'line_defers_online_payment',
    ];

    protected $casts = [
        'option_value_ids' => 'array',
        'bundle_snapshot' => 'array',
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_defers_online_payment' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        $fk = SchemaCache::hasColumn($this->getTable(), 'product_id')
            ? 'product_id'
            : 'animal_listing_id';

        return $this->belongsTo(Product::class, $fk);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class, 'bundle_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Фото позиції для листа: знімок варіанта, інакше товар або комплект.
     */
    public function mailPhotoStoragePath(): ?string
    {
        if ((int) $this->bundle_id > 0) {
            $bundle = $this->relationLoaded('bundle') ? $this->bundle : $this->bundle()->first();

            return $bundle?->firstCatalogPhotoPath();
        }

        if ((int) $this->variant_id > 0) {
            $variant = $this->relationLoaded('variant') ? $this->variant : $this->variant()->first();
            $photos = StoragePublicPath::normalizeList($variant?->photos ?? null);
            if ($photos !== []) {
                return $photos[0];
            }
        }

        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();

        return $product?->firstCatalogPhotoPath();
    }

    /**
     * Текст для колонки «Опції» в адмінці: значення з БД за option_value_ids, інакше title_snapshot;
     * для комплекту — склад з bundle_snapshot.
     */
    public function adminOptionsSummary(): string
    {
        if ($this->bundle_id !== null && (int) $this->bundle_id > 0) {
            return $this->adminBundleCompositionSummary();
        }

        $ids = $this->option_value_ids ?? [];
        if (! is_array($ids) || $ids === []) {
            return $this->adminFallbackTitleLine();
        }

        $intIds = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
        if ($intIds === []) {
            return $this->adminFallbackTitleLine();
        }

        $values = OptionValue::query()
            ->whereIn('id', $intIds)
            ->with('group')
            ->get()
            ->keyBy('id');

        if ($values->count() !== count($intIds)) {
            return $this->adminFallbackTitleLine();
        }

        $parts = [];
        foreach ($intIds as $id) {
            $value = $values->get($id);
            if (! $value) {
                return $this->adminFallbackTitleLine();
            }
            $group = $value->group;
            if ($group && $group->slug === 'category') {
                continue;
            }
            $groupName = trim((string) ($group?->name ?? ''));
            $parts[] = OptionLabel::join($groupName, (string) $value->name);
        }

        if ($parts === []) {
            return $this->adminFallbackTitleLine();
        }

        return implode('; ', $parts);
    }

    private function adminBundleCompositionSummary(): string
    {
        $snap = $this->bundle_snapshot ?? [];
        if (! is_array($snap)) {
            return $this->adminFallbackTitleLine();
        }

        $lines = [];
        foreach ($snap['items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $qty = max(1, (int) ($row['qty'] ?? 1));
            if ($title === '') {
                continue;
            }

            $optionLabels = is_array($row['option_labels'] ?? null)
                ? array_values(array_filter(array_map(
                    fn ($label): string => trim((string) $label),
                    $row['option_labels']
                )))
                : [];

            if ($optionLabels !== []) {
                $title .= ' ('.implode(', ', $optionLabels).')';
            }

            $lines[] = $title.' × '.$qty;
        }

        $header = trim((string) ($snap['title'] ?? ''));
        $detail = implode('; ', $lines);

        if ($detail !== '') {
            return $header !== '' ? $header.' — '.$detail : $detail;
        }

        return $header !== '' ? $header : $this->adminFallbackTitleLine();
    }

    private function adminFallbackTitleLine(): string
    {
        $t = trim((string) $this->title_snapshot);

        return $t !== '' ? $t : '—';
    }

    /**
     * URL прев’ю для адмінки (поточний товар/комплект у каталозі).
     */
    public function adminCatalogPhotoUrl(): ?string
    {
        if ($this->bundle_id !== null && (int) $this->bundle_id > 0) {
            $bundle = $this->relationLoaded('bundle')
                ? $this->bundle
                : $this->bundle()->with(['items.product.variants'])->first();

            if ($bundle === null) {
                return null;
            }

            return ListingPhotoDerivative::cardUrlForPath($bundle->firstCatalogPhotoPath()) ?: null;
        }

        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();
        if ($product === null) {
            return null;
        }

        return ListingPhotoDerivative::cardUrlForPath($product->firstCatalogPhotoPath()) ?: null;
    }
}
