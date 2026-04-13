<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\ProductVariant;
use App\Models\PromotionTarget;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BundlePricingService
{
    public function __construct(
        private readonly VariantPricingService $variantPricing,
        private readonly ListingOptionSelectionService $listingOptionSelection,
    ) {}

    /**
     * @return array{subtotal: float, discount: float, total: float}
     */
    public function quote(Bundle $bundle, ?Carbon $at = null): array
    {
        $at ??= now();
        $bundle->loadMissing(['items.product.variants']);

        $subtotal = 0.0;
        foreach ($bundle->items as $item) {
            $subtotal += $this->lineSubtotal($item, $at);
        }

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
        $variant = $this->listingOptionSelection->resolveVariantForLine($product, []);

        // Для комплектів у формі обирається лише товар, без конкретних option_value_ids.
        // Тому використовуємо SKU лише коли він однозначно визначається порожнім вибором,
        // інакше беремо базову ціну товару як у каталозі.
        if ($variant instanceof ProductVariant && $variant->isSellable()) {
            return $this->variantPricing->quoteProductVariant($variant, $at)->effectivePrice;
        }

        return $this->variantPricing->quoteProduct($product, $at)->effectivePrice;
    }

    /**
     * @return Collection<int, PromotionTarget>
     */
    private function loadBundlePromotionTargets(int $bundleId, Carbon $at): Collection
    {
        $moment = $at;

        return PromotionTarget::query()
            ->where('target_type', 'bundle')
            ->where('target_id', $bundleId)
            ->where(function ($q) use ($moment): void {
                $q->whereNull('promotion_targets.ends_at')
                    ->orWhere('promotion_targets.ends_at', '>=', $moment);
            })
            ->whereHas('promotion', fn ($q) => $q->activeAt($at))
            ->with('promotion')
            ->get();
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
