<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly ListingOptionSelectionService $listingOptionSelection,
    ) {}

    /**
     * Скільки ще можна продати зараз. null = залишок не трекається (кількість не задана або предзамовлення).
     */
    public function availableQuantity(Product $product, ?ProductVariant $variant): ?int
    {
        if ($product->allowsPreorder()) {
            return null;
        }

        if ($variant === null) {
            return $product->stock_quantity === null ? null : max(0, (int) $product->stock_quantity);
        }

        if ($variant->allows_preorder) {
            return null;
        }

        if ($variant->quantity === null) {
            return null;
        }

        return max(0, (int) $variant->quantity);
    }

    public function isPurchasable(Product $product, ?ProductVariant $variant): bool
    {
        if (! $product->is_available) {
            return false;
        }

        if ($product->allowsPreorder()) {
            return true;
        }

        if ($variant !== null) {
            if ($variant->allows_preorder) {
                return true;
            }

            if (! $variant->isSellable()) {
                return false;
            }

            $available = $this->availableQuantity($product, $variant);

            return $available === null || $available > 0;
        }

        $product->loadMissing('variants');
        $visible = $product->variants->filter(
            fn (ProductVariant $candidate): bool => $candidate->isVisibleOnStorefront()
        );
        if ($visible->isNotEmpty()) {
            return false;
        }

        if (! $product->isOrderable()) {
            return false;
        }

        $available = $this->availableQuantity($product, null);

        return $available === null || $available > 0;
    }

    public function purchaseError(Product $product, ?ProductVariant $variant, int $qty, int $alreadyInCart = 0): ?string
    {
        if (! $product->is_available) {
            return 'Цей товар зараз прихований з каталогу.';
        }

        if (! $this->isPurchasable($product, $variant)) {
            return (string) __('shop.cart_product_out_of_stock');
        }

        $available = $this->availableQuantity($product, $variant);
        if ($available === null) {
            return null;
        }

        $wanted = max(1, $qty) + max(0, $alreadyInCart);
        if ($wanted > $available) {
            return (string) __('shop.cart_qty_exceeds_stock', ['qty' => $available]);
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    public function firstUnresolvedPurchaseError(Collection $lines): ?string
    {
        foreach ($lines as $line) {
            $error = $this->purchaseErrorForResolvedLine($line);
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function purchaseErrorForResolvedLine(array $line, int $alreadyInCart = 0): ?string
    {
        if (($line['line_kind'] ?? 'product') === 'bundle') {
            return $this->bundlePurchaseError($line, $alreadyInCart);
        }

        $product = $line['product'] ?? null;
        if (! $product instanceof Product) {
            return (string) __('shop.cart_product_out_of_stock');
        }

        $variant = $line['variant'] instanceof ProductVariant ? $line['variant'] : null;
        $qty = max(1, (int) ($line['qty'] ?? 1));

        return $this->purchaseError($product, $variant, $qty, $alreadyInCart);
    }

    /**
     * Перевірка залишків компонентів комплекту з items.product.variants, завантаженими на $bundle.
     */
    public function bundlePurchaseErrorForBundle(Bundle $bundle, int $bundleQty): ?string
    {
        return $this->bundlePurchaseError([
            'qty' => $bundleQty,
            'bundle_items' => $bundle->items->map(function ($item): array {
                $product = $item->product;
                $optionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];
                $variant = $product
                    ? $this->listingOptionSelection->resolveVariantForLine($product, $optionValueIds)
                    : null;

                return [
                    'product_id' => (int) ($item->product_id ?? 0),
                    'variant_id' => $variant?->id,
                    'option_value_ids' => $optionValueIds,
                    'qty' => max(1, (int) $item->qty),
                ];
            })->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function bundlePurchaseError(array $line, int $alreadyBundlesInCart = 0): ?string
    {
        $bundleQty = max(1, (int) ($line['qty'] ?? 1)) + max(0, $alreadyBundlesInCart);
        foreach ($this->bundleComponentRows($line) as $row) {
            $product = $row['product'];
            $variant = $row['variant'];
            $error = $this->purchaseError($product, $variant, $row['qty'] * $bundleQty);
            if ($error !== null) {
                return (string) __('shop.cart_bundle_component_out_of_stock', [
                    'title' => $product->title,
                ]);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    public function decrementForResolvedLines(Collection $lines): void
    {
        foreach ($lines as $line) {
            $error = $this->purchaseErrorForResolvedLine($line);
            if ($error !== null) {
                throw ValidationException::withMessages([
                    'cart' => $error,
                ]);
            }
        }

        $this->applyStockDeltas($this->neededQuantitiesFromResolvedLines($lines), -1);
    }

    public function commitForOrder(Order $order, Collection $lines): void
    {
        if ($order->inventory_committed) {
            return;
        }

        $this->decrementForResolvedLines($lines);
        $order->forceFill(['inventory_committed' => true])->save();
    }

    public function restoreForOrder(Order $order): void
    {
        if (! $order->inventory_committed) {
            return;
        }

        $order->loadMissing(['items.product.variants']);
        $this->applyStockDeltas($this->neededQuantitiesFromOrderItems($order->items), 1);
        $order->forceFill(['inventory_committed' => false])->save();
    }

    /**
     * Знову бере товар зі складу для замовлення, яке повертається в роботу.
     * Лише змінює модель: викликається під час її збереження (див. Order::booted).
     */
    public function takeForOrder(Order $order): void
    {
        if ($order->inventory_committed) {
            return;
        }

        $order->loadMissing(['items.product.variants']);
        $needed = $this->neededQuantitiesFromOrderItems($order->items);

        DB::transaction(fn () => $this->applyStockDeltas($needed, -1));

        $order->inventory_committed = true;
    }

    public function shouldHoldInventory(Order $order): bool
    {
        if ((string) $order->status === Order::STATUS_CANCELLED) {
            return false;
        }

        return ! in_array((string) $order->payment_status, ['failed', 'refunded'], true);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return array<int|string, int>
     */
    public function neededQuantitiesFromOrderItems(Collection $items): array
    {
        $needed = [];
        foreach ($items as $item) {
            if (! $item instanceof OrderItem) {
                continue;
            }
            foreach ($this->movementsFromOrderItem($item) as $key => $qty) {
                $needed[$key] = ($needed[$key] ?? 0) + $qty;
            }
        }

        return $needed;
    }

    /**
     * @return array<int|string, int>
     */
    public function movementsFromOrderItem(OrderItem $item): array
    {
        if ((int) ($item->bundle_id ?? 0) > 0) {
            $line = [
                'line_kind' => 'bundle',
                'qty' => max(1, (int) $item->qty),
                'bundle_snapshot' => is_array($item->bundle_snapshot) ? $item->bundle_snapshot : [],
                'bundle' => $item->relationLoaded('bundle') ? $item->bundle : $item->bundle,
            ];

            return $this->neededQuantitiesFromResolvedLines(collect([$line]));
        }

        $product = $item->relationLoaded('product') ? $item->product : $item->product()->with('variants')->first();
        if (! $product instanceof Product) {
            return [];
        }

        $variant = $this->resolveOrderItemVariant($item, $product);
        if (! $variant instanceof ProductVariant) {
            return $this->availableQuantity($product, null) === null
                ? []
                : [$this->stockKey($product, null) => max(1, (int) $item->qty)];
        }

        if ($this->availableQuantity($product, $variant) === null && $variant->quantity === null) {
            return [];
        }

        if ($product->allowsPreorder() || $variant->allows_preorder) {
            return [];
        }

        if ($variant->quantity === null) {
            return [];
        }

        return [(int) $variant->id => max(1, (int) $item->qty)];
    }

    public function syncCommittedOrderItem(Order $order, ?OrderItem $before, ?OrderItem $after): void
    {
        if (! $order->inventory_committed || ! $this->shouldHoldInventory($order)) {
            return;
        }

        $old = $before instanceof OrderItem ? $this->movementsFromOrderItem($before) : [];
        $new = $after instanceof OrderItem ? $this->movementsFromOrderItem($after) : [];
        $delta = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $variantId) {
            $change = (int) ($new[$variantId] ?? 0) - (int) ($old[$variantId] ?? 0);
            if ($change !== 0) {
                $delta[$variantId] = $change;
            }
        }

        if ($delta === []) {
            return;
        }

        $take = [];
        $give = [];
        foreach ($delta as $variantId => $change) {
            if ($change > 0) {
                $take[$variantId] = $change;
            } else {
                $give[$variantId] = abs($change);
            }
        }

        if ($take !== []) {
            $this->applyStockDeltas($take, -1);
        }
        if ($give !== []) {
            $this->applyStockDeltas($give, 1);
        }
    }

    /**
     * Ключ — id варіанта або «p{id}» для товару без варіантів (див. stockKey).
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array<int|string, int>
     */
    public function neededQuantitiesFromResolvedLines(Collection $lines): array
    {
        $needed = [];

        foreach ($lines as $line) {
            if (($line['line_kind'] ?? 'product') === 'bundle') {
                $bundleQty = max(1, (int) ($line['qty'] ?? 1));
                foreach ($this->bundleComponentRows($line) as $row) {
                    if ($this->availableQuantity($row['product'], $row['variant']) === null) {
                        continue;
                    }
                    $key = $this->stockKey($row['product'], $row['variant']);
                    $needed[$key] = ($needed[$key] ?? 0) + ($row['qty'] * $bundleQty);
                }

                continue;
            }

            $product = $line['product'] ?? null;
            $variant = $line['variant'] ?? null;
            $qty = max(1, (int) ($line['qty'] ?? 1));

            if (! $product instanceof Product) {
                continue;
            }

            $variant = $variant instanceof ProductVariant ? $variant : null;
            if ($this->availableQuantity($product, $variant) === null) {
                continue;
            }

            $key = $this->stockKey($product, $variant);
            $needed[$key] = ($needed[$key] ?? 0) + $qty;
        }

        return $needed;
    }

    private function stockKey(Product $product, ?ProductVariant $variant): int|string
    {
        return $variant instanceof ProductVariant ? (int) $variant->id : 'p'.$product->id;
    }

    /**
     * @param  array<int|string, int>  $needed
     */
    private function applyStockDeltas(array $needed, int $sign): void
    {
        if ($needed === [] || $sign === 0) {
            return;
        }

        $this->applyVariantDeltas(array_filter($needed, 'is_int', ARRAY_FILTER_USE_KEY), $sign);

        $byProduct = [];
        foreach ($needed as $key => $qty) {
            if (is_string($key)) {
                $byProduct[(int) substr($key, 1)] = $qty;
            }
        }
        $this->applyProductDeltas($byProduct, $sign);
    }

    /**
     * @param  array<int, int>  $neededByProduct
     */
    private function applyProductDeltas(array $neededByProduct, int $sign): void
    {
        if ($neededByProduct === []) {
            return;
        }

        $locked = Product::query()
            ->whereIn('id', array_keys($neededByProduct))
            ->lockForUpdate()
            ->get(['id', 'stock_quantity', 'stock_status'])
            ->keyBy('id');

        foreach ($neededByProduct as $productId => $qty) {
            $product = $locked->get($productId);
            if (! $product instanceof Product || $product->stock_quantity === null) {
                if ($sign < 0) {
                    throw ValidationException::withMessages([
                        'cart' => (string) __('shop.cart_product_out_of_stock'),
                    ]);
                }

                continue;
            }

            $onHand = max(0, (int) $product->stock_quantity);
            if ($sign < 0 && $onHand < $qty) {
                throw ValidationException::withMessages([
                    'cart' => (string) __('shop.cart_qty_exceeds_stock', ['qty' => $onHand]),
                ]);
            }

            $next = max(0, $onHand + ($qty * $sign));
            $status = $product->stockStatus();
            if ($next <= 0 && $status === Product::STOCK_IN_STOCK) {
                $status = Product::STOCK_OUT_OF_STOCK;
            } elseif ($next > 0 && $sign > 0 && $status === Product::STOCK_OUT_OF_STOCK) {
                $status = Product::STOCK_IN_STOCK;
            }

            // Без подій моделі: зміна залишку не має зачіпати фото та варіанти товару.
            Product::query()->whereKey($productId)->update([
                'stock_quantity' => $next,
                'stock_status' => $status,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $neededByVariant
     */
    private function applyVariantDeltas(array $neededByVariant, int $sign): void
    {
        if ($neededByVariant === [] || $sign === 0) {
            return;
        }

        $locked = ProductVariant::query()
            ->whereIn('id', array_keys($neededByVariant))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($neededByVariant as $variantId => $qty) {
            $variant = $locked->get($variantId);
            if (! $variant instanceof ProductVariant) {
                throw ValidationException::withMessages([
                    'cart' => (string) __('shop.cart_product_out_of_stock'),
                ]);
            }

            $onHand = max(0, (int) ($variant->quantity ?? 0));
            $change = $qty * $sign;

            if ($sign < 0 && $onHand < $qty) {
                throw ValidationException::withMessages([
                    'cart' => (string) __('shop.cart_qty_exceeds_stock', ['qty' => $onHand]),
                ]);
            }

            $next = max(0, $onHand + $change);
            $variant->quantity = $next;
            if ($next <= 0 && ! $variant->allows_preorder) {
                $variant->is_available = false;
            } elseif ($next > 0 && $sign > 0 && ! $variant->allows_preorder) {
                $variant->is_available = true;
            }
            $variant->save();
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<array{product: Product, variant: ProductVariant|null, qty: int}>
     */
    private function bundleComponentRows(array $line): array
    {
        $rows = [];
        $snapshotItems = [];
        $snapshot = $line['bundle_snapshot'] ?? null;
        if (is_array($snapshot) && is_array($snapshot['items'] ?? null)) {
            $snapshotItems = $snapshot['items'];
        } elseif (is_array($line['bundle_items'] ?? null)) {
            $snapshotItems = $line['bundle_items'];
        }

        foreach ($snapshotItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $productId = (int) ($item['product_id'] ?? 0);
            $product = Product::query()->with('variants')->find($productId);
            if (! $product instanceof Product) {
                continue;
            }

            $optionValueIds = is_array($item['option_value_ids'] ?? null) ? $item['option_value_ids'] : [];
            $variant = $this->listingOptionSelection->resolveVariantForLine($product, $optionValueIds);
            $requestedVariantId = (int) ($item['variant_id'] ?? 0);
            if ($requestedVariantId > 0 && ($variant === null || (int) $variant->id !== $requestedVariantId)) {
                $picked = $product->variants->first(
                    fn (ProductVariant $candidate): bool => (int) $candidate->id === $requestedVariantId
                        && $candidate->isVisibleOnStorefront()
                );
                if ($picked instanceof ProductVariant && $optionValueIds === []) {
                    $variant = $picked;
                }
            }

            $rows[] = [
                'product' => $product,
                'variant' => $variant,
                'qty' => max(1, (int) ($item['qty'] ?? 1)),
            ];
        }

        return $rows;
    }

    private function resolveOrderItemVariant(OrderItem $item, Product $product): ?ProductVariant
    {
        $storedId = (int) ($item->variant_id ?? 0);
        if ($storedId > 0) {
            $product->loadMissing('variants');
            $stored = $product->variants->first(
                fn (ProductVariant $candidate): bool => (int) $candidate->id === $storedId
            );
            if ($stored instanceof ProductVariant) {
                return $stored;
            }
        }

        $optionValueIds = is_array($item->option_value_ids) ? $item->option_value_ids : [];

        return $this->listingOptionSelection->resolveVariantForLine($product, $optionValueIds);
    }
}
