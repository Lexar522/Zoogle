<?php

namespace App\Filament\Support;

use App\Models\Product;
use App\Support\CatalogProductsTable;
use App\Support\PublicStorageUrl;
use Illuminate\Support\Facades\Schema;

final class ProductAdminSelectSearch
{
    /**
     * @return array<int, string>
     */
    public static function search(string $search, ?int $excludeProductId = null): array
    {
        $term = trim($search);
        if ($term === '') {
            return [];
        }

        return self::mapProducts(
            self::query($term, $excludeProductId)->get(['id', 'title', 'sku']),
            withPhoto: false,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function searchWithPhoto(string $search, ?int $excludeProductId = null): array
    {
        $term = trim($search);

        return self::mapProducts(
            self::query($term, $excludeProductId)
                ->with(['variants' => fn ($query) => self::constrainVariantPreview($query)])
                ->get(['id', 'title', 'sku', 'photos']),
            withPhoto: true,
        );
    }

    /**
     * Назви до 2000 товарів для селектів адмінки. Лише id і title, без описів і фото.
     *
     * @return array<string, string>
     */
    public static function titleChoices(): array
    {
        /** @var array<string, string> $choices */
        $choices = once(function (): array {
            $map = [];
            $rows = Product::query()->orderBy('title')->limit(2000)->get(['id', 'title']);
            foreach ($rows as $product) {
                $title = trim((string) $product->title);
                $map[(string) $product->id] = $title !== '' ? $title : '#'.$product->id;
            }

            return $map;
        });

        return $choices;
    }

    public static function titleForId(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $key = (string) $id;
        $choices = self::titleChoices();
        if (array_key_exists($key, $choices)) {
            return $choices[$key];
        }

        $product = Product::query()->whereKey($id)->first(['id', 'title']);
        if ($product === null) {
            return null;
        }

        $title = trim((string) $product->title);

        return $title !== '' ? $title : '#'.$product->id;
    }

    public static function labelForId(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $product = Product::query()->whereKey($id)->first(['id', 'title', 'sku']);

        return $product !== null ? self::formatLabel($product) : null;
    }

    public static function labelHtmlForId(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $product = Product::query()
            ->whereKey($id)
            ->with(['variants' => fn ($query) => self::constrainVariantPreview($query)])
            ->first(['id', 'title', 'sku', 'photos']);

        return $product !== null ? self::formatLabelHtml($product) : null;
    }

    public static function formatLabel(Product $product): string
    {
        $title = trim((string) ($product->title ?? ''));
        $sku = trim((string) ($product->sku ?? ''));

        if ($title === '') {
            return $sku !== '' ? $sku : '#'.$product->id;
        }

        if ($sku === '') {
            return $title;
        }

        return $title.' · '.$sku;
    }

    public static function formatLabelHtml(Product $product): string
    {
        $label = e(self::formatLabel($product));
        $photoUrl = PublicStorageUrl::forPath($product->firstListedPhotoPath());

        $thumb = $photoUrl !== null
            ? '<img src="'.e($photoUrl).'" alt="" width="40" height="40" style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex:0 0 auto;background:#f3f4f6;" />'
            : '<span style="display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border-radius:6px;background:#e5e7eb;color:#6b7280;font-size:11px;flex:0 0 auto;">—</span>';

        return '<span style="display:inline-flex;align-items:center;gap:10px;min-width:0;">'
            .$thumb
            .'<span style="min-width:0;line-height:1.25;">'.$label.'</span>'
            .'</span>';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private static function query(string $search, ?int $excludeProductId = null)
    {
        $term = trim($search);
        $table = CatalogProductsTable::name();
        $hasSku = Schema::hasColumn($table, 'sku');

        $query = Product::query()->orderBy('title');

        if ($excludeProductId !== null && $excludeProductId > 0) {
            $query->whereKeyNot($excludeProductId);
        }

        if ($term !== '') {
            $query->where(function ($w) use ($term, $hasSku): void {
                $w->where('title', 'like', '%'.$term.'%');

                if ($hasSku) {
                    $w->orWhere('sku', 'like', '%'.$term.'%')
                        ->orWhere('sku', $term);
                }
            });
        }

        return $query->limit(50);
    }

    private static function constrainVariantPreview($query): void
    {
        $variant = $query->getModel();
        $fk = $variant->product()->getForeignKeyName();
        $columns = [$variant->getKeyName() ?: 'id', $fk];
        if (Schema::hasColumn($variant->getTable(), 'photos')) {
            $columns[] = 'photos';
        }
        $query->select(array_values(array_unique($columns)));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return array<int, string>
     */
    private static function mapProducts($products, bool $withPhoto): array
    {
        return $products
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $withPhoto
                    ? self::formatLabelHtml($product)
                    : self::formatLabel($product),
            ])
            ->all();
    }
}
