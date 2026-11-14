<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ShopCart;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CartDrawerService
{
    public function __construct(
        private readonly CartLineResolver $cartLineResolver,
        private readonly BundlePricingService $bundlePricing,
        private readonly ListingOptionSelectionService $listingOptionSelection,
        private readonly BundleShowPageService $bundleShowPage,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @return array{
     *     items: Collection<int, array<string, mixed>>,
     *     summary: array{lines_count: int, items_count: int, total: float, is_empty: bool}
     * }
     */
    public function forRequest(Request $request, bool $forCheckout = false): array
    {
        $cart = $request->hasSession()
            ? ShopCart::normalize(is_array($request->session()->get('cart')) ? $request->session()->get('cart') : [])
            : [];

        return $this->fromCart($cart, $forCheckout);
    }

    /**
     * @param  array<string, array{
     *     line_kind: 'product'|'bundle',
     *     product_id?: int,
     *     bundle_id?: int,
     *     qty: int,
     *     option_value_ids: list<int>
     * }>  $cart
     * @return array{
     *     items: Collection<int, array<string, mixed>>,
     *     summary: array{lines_count: int, items_count: int, total: float, is_empty: bool}
     * }
     */
    public function fromCart(array $cart, bool $forCheckout = false): array
    {
        $items = $this->resolveItems($cart, $forCheckout);
        $blocked = $items->first(fn (array $line): bool => ! empty($line['unavailable']));

        return [
            'items' => $items,
            'summary' => [
                'lines_count' => $items->count(),
                'items_count' => (int) $items->sum('qty'),
                'total' => round((float) $items->sum(
                    fn (array $line): float => empty($line['unavailable']) ? (float) ($line['line_total'] ?? 0) : 0.0
                ), 2),
                'is_empty' => $items->isEmpty(),
                'can_checkout' => $items->isNotEmpty() && $blocked === null,
                'blocked_reason' => is_array($blocked) ? (string) ($blocked['unavailable_reason'] ?? '') : null,
            ],
        ];
    }

    /**
     * @param  array<string, array{
     *     line_kind: 'product'|'bundle',
     *     product_id?: int,
     *     bundle_id?: int,
     *     qty: int,
     *     option_value_ids: list<int>
     * }>  $cart
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveItems(array $cart, bool $forCheckout = false): Collection
    {
        $ids = collect($cart)
            ->filter(fn (array $row): bool => ($row['line_kind'] ?? 'product') === 'product')
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $bundleIds = collect($cart)
            ->filter(fn (array $row): bool => ($row['line_kind'] ?? 'product') === 'bundle')
            ->pluck('bundle_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] && $bundleIds === []) {
            return collect();
        }

        $products = collect();
        if ($ids !== []) {
            $products = Product::query()
                ->whereIn('id', $ids)
                ->where('is_available', true)
                ->with('variants')
                ->get()
                ->keyBy('id');
        }

        $bundles = collect();
        if ($bundleIds !== []) {
            $bundles = Bundle::query()
                ->whereIn('id', $bundleIds)
                ->with(['items.product.variants'])
                ->get()
                ->keyBy('id');
        }

        return collect($cart)
            ->map(function (array $row, string $cartKey) use ($products, $bundles, $forCheckout): ?array {
                $lineKind = (string) ($row['line_kind'] ?? 'product');
                if ($lineKind === 'bundle') {
                    $bundle = $bundles->get((int) ($row['bundle_id'] ?? 0));
                    if (! $bundle) {
                        return null;
                    }

                    return $this->resolveBundleLine($row, $cartKey, $bundle, $forCheckout);
                }

                $product = $products->get((int) ($row['product_id'] ?? 0));
                if (! $product) {
                    return null;
                }

                $line = $this->cartLineResolver->resolveLine($row, $product, requireSellable: false);
                if (! $line) {
                    return null;
                }
                $optionDisplayItems = $this->listingOptionSelection->selectionDisplayItems(
                    $product,
                    is_array($line['option_value_ids'] ?? null) ? $line['option_value_ids'] : []
                );

                return $this->decorateInventory(array_merge($line, [
                    'line_kind' => 'product',
                    'key' => $cartKey,
                    'title' => (string) $product->title,
                    'title_url' => route('catalog.show', $product->slug),
                    'photo' => $this->linePhotoPath($line, $optionDisplayItems),
                    'option_badges' => array_values(array_filter(
                        $optionDisplayItems,
                        fn (array $item): bool => ! $this->isSwatchDisplayItem($item)
                    )),
                    'option_swatches' => array_values(array_filter(
                        $optionDisplayItems,
                        fn (array $item): bool => $this->isSwatchDisplayItem($item)
                    )),
                    'bundle' => null,
                    'bundle_items' => [],
                    'bundle_snapshot' => null,
                ]));
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array{line_kind: 'bundle', bundle_id?: int, qty: int, option_value_ids: list<int>}  $row
     * @return array<string, mixed>|null
     */
    private function resolveBundleLine(array $row, string $cartKey, Bundle $bundle, bool $forCheckout = false): ?array
    {
        $bundleItems = [];
        foreach ($bundle->items->sortBy('sort_order') as $item) {
            $product = $item->product;
            if (! $product) {
                return null;
            }

            $lineQuote = $this->bundlePricing->lineQuote($item);
            $itemOptionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];
            $variant = $this->listingOptionSelection->resolveVariantForLine($product, $itemOptionValueIds);
            $bundleItems[] = [
                'product_id' => (int) $product->id,
                'variant_id' => $variant?->id,
                'slug' => (string) $product->slug,
                'title' => (string) $product->title,
                'option_value_ids' => $itemOptionValueIds,
                'option_labels' => $this->listingOptionSelection->describeSelection($product, $itemOptionValueIds),
                'qty' => max(1, (int) $item->qty),
                'unit_price' => round((float) ($lineQuote['unit'] ?? 0), 2),
                'line_total' => round((float) ($lineQuote['total'] ?? 0), 2),
            ];
        }

        if ($bundleItems === []) {
            return null;
        }

        $optionValueIds = is_array($row['option_value_ids'] ?? null) ? $row['option_value_ids'] : [];
        $optionValueIds = $this->bundleShowPage->sanitizeSelectionForBundle($bundle, $optionValueIds);
        $quote = $this->bundlePricing->quote($bundle, null, $optionValueIds);
        $optionDisplayItems = $this->bundleShowPage->selectionDisplayItems($bundle, $optionValueIds);
        $optionLabels = array_values(array_filter(array_map(
            fn (array $item): string => trim((string) ($item['label'] ?? '')),
            $optionDisplayItems
        )));
        $qty = max(1, (int) ($row['qty'] ?? 1));
        $unitPrice = round((float) ($quote['total'] ?? 0), 2);
        $oldUnitPrice = null;
        if ((float) ($quote['discount'] ?? 0) > 0.0001) {
            $oldUnitPrice = round((float) ($quote['subtotal'] ?? 0), 2);
        }

        $resolved = [
            'line_kind' => 'bundle',
            'key' => $cartKey,
            'bundle' => $bundle,
            'product' => null,
            'variant' => null,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'old_unit_price' => $oldUnitPrice,
            'line_total' => round($unitPrice * $qty, 2),
            'old_line_total' => $oldUnitPrice !== null ? round($oldUnitPrice * $qty, 2) : null,
            'option_value_ids' => $optionValueIds,
            'option_labels' => $optionLabels,
            'option_badges' => array_values(array_filter(
                $optionDisplayItems,
                fn (array $item): bool => ($item['value_type'] ?? 'text') !== 'color'
            )),
            'option_swatches' => array_values(array_filter(
                $optionDisplayItems,
                fn (array $item): bool => ($item['value_type'] ?? 'text') === 'color'
            )),
            'photo' => $bundle->firstCatalogPhotoPath(),
            'title' => (string) $bundle->title,
            'title_url' => route('bundles.show', $bundle->slug),
            'bundle_items' => $bundleItems,
            'bundle_snapshot' => $this->makeBundleSnapshot($bundle, $bundleItems, $quote, $optionValueIds, $optionLabels),
        ];

        if (! $bundle->is_active || ! $bundle->is_visible) {
            $resolved['unavailable'] = true;
            $resolved['unavailable_reason'] = (string) __('shop.cart_product_out_of_stock');

            return $resolved;
        }

        return $this->decorateInventory($resolved);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function decorateInventory(array $line): array
    {
        $error = $this->inventory->purchaseErrorForResolvedLine($line);
        $line['unavailable'] = $error !== null;
        $line['unavailable_reason'] = $error;

        return $line;
    }

    /**
     * @param  list<array<string, mixed>>  $bundleItems
     * @param  array{subtotal: float, discount: float, total: float}  $quote
     * @param  list<int>  $optionValueIds
     * @param  list<string>  $optionLabels
     * @return array<string, mixed>
     */
    private function makeBundleSnapshot(Bundle $bundle, array $bundleItems, array $quote, array $optionValueIds = [], array $optionLabels = []): array
    {
        return [
            'bundle_id' => (int) $bundle->id,
            'title' => (string) $bundle->title,
            'slug' => (string) $bundle->slug,
            'sku' => (string) ($bundle->sku ?? ''),
            'subtotal' => round((float) ($quote['subtotal'] ?? 0), 2),
            'discount' => round((float) ($quote['discount'] ?? 0), 2),
            'total' => round((float) ($quote['total'] ?? 0), 2),
            'option_value_ids' => $optionValueIds,
            'option_labels' => $optionLabels,
            'items' => $bundleItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  list<array<string, mixed>>  $optionDisplayItems
     */
    private function linePhotoPath(array $line, array $optionDisplayItems): ?string
    {
        foreach ($optionDisplayItems as $item) {
            $swatchImage = $item['swatch_image'] ?? null;
            if (is_string($swatchImage) && $swatchImage !== '') {
                return ltrim($swatchImage, '/');
            }
        }

        $product = $line['product'] ?? null;
        if ($product instanceof Product) {
            $primary = $product->firstCatalogPhotoPath();
            if ($primary !== null) {
                return $primary;
            }
        }

        $variant = $line['variant'] ?? null;
        if ($variant instanceof ProductVariant) {
            foreach ($variant->photos ?? [] as $path) {
                if (is_string($path) && $path !== '') {
                    return ltrim($path, '/');
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isSwatchDisplayItem(array $item): bool
    {
        if (($item['value_type'] ?? 'text') !== 'color') {
            return false;
        }

        return filled($item['color_hex'] ?? null) || filled($item['swatch_image'] ?? null);
    }
}
