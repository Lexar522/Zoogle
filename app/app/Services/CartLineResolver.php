<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

class CartLineResolver
{
    public function __construct(
        private readonly ListingOptionSelectionService $listingOptionSelection,
        private readonly VariantPricingService $variantPricing,
    ) {}

    /**
     * @param  array{product_id: int, qty: int, option_value_ids: list<int>}  $row
     * @return array{
     *     product: Product,
     *     variant: ProductVariant|null,
     *     qty: int,
     *     unit_price: float,
     *     old_unit_price: float|null,
     *     line_total: float,
     *     old_line_total: float|null,
     *     option_value_ids: list<int>,
     *     option_labels: list<string>
     * }|null
     */
    public function resolveLine(array $row, Product $product, bool $requireSellable = false): ?array
    {
        $lineSelection = $this->listingOptionSelection->sanitizeSelectionForProduct(
            $product,
            is_array($row['option_value_ids'] ?? null) ? $row['option_value_ids'] : []
        );

        $variant = $this->listingOptionSelection->resolveVariantForLine($product, $lineSelection);
        $quote = null;

        if ($variant !== null) {
            if ($requireSellable && ! $variant->isSellable()) {
                return null;
            }
            $quote = $this->variantPricing->quoteProductVariant($variant);
        } else {
            if ($requireSellable && ! $product->is_available) {
                return null;
            }
            $quote = $this->variantPricing->quoteProduct($product);
        }

        $addon = $this->listingOptionSelection->priceAddonForSelection($product, $lineSelection);
        $unitPrice = round(
            $quote->effectivePrice + $addon,
            2
        );
        $qty = max(1, (int) ($row['qty'] ?? 1));
        $oldUnitPrice = null;
        if ($quote->strikePrice !== null && $quote->strikePrice > $quote->effectivePrice + 0.0001) {
            $oldUnitPrice = round($quote->strikePrice + $addon, 2);
        }

        return [
            'product' => $product,
            'variant' => $variant,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'old_unit_price' => $oldUnitPrice,
            'line_total' => round($unitPrice * $qty, 2),
            'old_line_total' => $oldUnitPrice !== null ? round($oldUnitPrice * $qty, 2) : null,
            'option_value_ids' => $lineSelection,
            'option_labels' => $this->listingOptionSelection->describeSelection($product, $lineSelection),
        ];
    }
}
