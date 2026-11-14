<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\ProductVariant;
use App\Models\PromotionTarget;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BundlePricingService
{
    public function __construct(
        private readonly VariantPricingService $variantPricing,
        private readonly ListingOptionSelectionService $listingOptionSelection,
        private readonly BundleShowPageService $bundleShowPage,
    ) {}

    /**
     * @return array{subtotal: float, discount: float, total: float}
     */
    public function quote(Bundle $bundle, ?Carbon $at = null, array $optionValueIds = []): array
    {
        $at ??= now();
        $bundle->loadMissing(['items.product.variants']);

        $subtotal = 0.0;
        foreach ($bundle->items as $item) {
            $subtotal += $this->lineSubtotal($item, $at);
        }

        $subtotal += $this->bundleShowPage->priceAddonForSelection($bundle, $optionValueIds);
        $subtotal = round($subtotal, 2);
        $targets = $this->loadBundlePromotionTargets((int) $bundle->id, $at);
        $priced = $this->variantPricing->quotePromotionTargetsOnAmount($subtotal, $targets, $at);
        $discount = round($subtotal - $priced->effectivePrice, 2);

        return [
            'subtotal' => $subtotal,
            'discount' => max(0, $discount),
            'total' => $priced->effectivePrice,
        ];
    }

    /**
     * Ціни багатьох комплектів: акції товарів, варіантів і самих комплектів беруться трьома запитами
     * замість окремого запиту на кожну позицію.
     *
     * @param  iterable<int, Bundle>  $bundles
     * @return array<int, array{subtotal: float, discount: float, total: float}>  за id комплекту
     */
    public function quoteMany(iterable $bundles, ?Carbon $at = null): array
    {
        $at ??= now();
        $bundles = EloquentCollection::make($bundles)->filter(fn ($bundle): bool => $bundle instanceof Bundle)->values();
        if ($bundles->isEmpty()) {
            return [];
        }

        $bundles->loadMissing(['items.product.variants']);

        $productIds = [];
        $variantIds = [];
        foreach ($bundles as $bundle) {
            foreach ($bundle->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->is_available) {
                    continue;
                }

                $optionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];
                $variant = $this->listingOptionSelection->resolveVariantForLine($product, $optionValueIds);
                if ($variant instanceof ProductVariant && $variant->isSellable()) {
                    $variantIds[] = (int) $variant->id;
                } else {
                    $productIds[] = (int) $product->id;
                }
            }
        }

        $this->variantPricing->preloadTargets('product', $productIds, $at);
        $this->variantPricing->preloadTargets('product_variant', $variantIds, $at);
        $this->variantPricing->preloadTargets('bundle', $bundles->map(fn (Bundle $bundle): int => (int) $bundle->id)->all(), $at);

        $out = [];
        foreach ($bundles as $bundle) {
            $out[(int) $bundle->id] = $this->quote($bundle, $at);
        }

        return $out;
    }

    /**
     * @return array{unit: float, total: float}
     */
    public function lineQuote(BundleItem $item, ?Carbon $at = null): array
    {
        $at ??= now();
        $qty = max(1, (int) $item->qty);

        $unit = $this->resolveUnitPrice($item, $at);

        return [
            'unit' => round($unit, 2),
            'total' => round($unit * $qty, 2),
        ];
    }

    private function lineSubtotal(BundleItem $item, Carbon $at): float
    {
        return $this->lineQuote($item, $at)['total'];
    }

    private function resolveUnitPrice(BundleItem $item, Carbon $at): float
    {
        $product = $item->product;
        if (! $product || ! $product->is_available) {
            return 0.0;
        }

        $product->loadMissing(['variants']);
        $optionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];
        $variant = $this->listingOptionSelection->resolveVariantForLine($product, $optionValueIds);

        if ($variant instanceof ProductVariant && $variant->isSellable()) {
            return $this->variantPricing->quoteProductVariant($variant, $at)->effectivePrice;
        }

        return $this->variantPricing->quoteProduct($product, $at)->effectivePrice
            + $this->listingOptionSelection->priceAddonForSelection($product, $optionValueIds);
    }

    /**
     * @return Collection<int, PromotionTarget>
     */
    private function loadBundlePromotionTargets(int $bundleId, Carbon $at): Collection
    {
        return $this->variantPricing->targetsFor('bundle', [$bundleId], $at)->get($bundleId, collect());
    }

    /**
     * Підзапит для whereExists: активна акція на комплект.
     *
     * @param  string  $idColumnSql  напр. bundles.id
     */
    public static function bindActivePromotionExists(QueryBuilder $sub, string $idColumnSql): void
    {
        $now = now();

        $sub->selectRaw('1')
            ->from('promotion_targets')
            ->join('promotions', 'promotions.id', '=', 'promotion_targets.promotion_id')
            ->whereColumn('promotion_targets.target_id', $idColumnSql)
            ->where('promotion_targets.target_type', '=', 'bundle')
            ->where('promotions.is_active', true)
            ->where(function ($w) use ($now): void {
                $w->whereNull('promotions.starts_at')
                    ->orWhere('promotions.starts_at', '<=', $now);
            })
            ->where(function ($w) use ($now): void {
                $w->whereNull('promotions.ends_at')
                    ->orWhere('promotions.ends_at', '>=', $now);
            })
            ->where(function ($w) use ($now): void {
                $w->whereNull('promotion_targets.ends_at')
                    ->orWhere('promotion_targets.ends_at', '>=', $now);
            });
    }
}
