<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class AdminOrderItemEditor
{
    public function __construct(
        private readonly ListingOptionSelectionService $options,
        private readonly CartLineResolver $cartLines,
        private readonly CategoryCheckoutRulesService $checkoutRules,
    ) {}

    /**
     * @return array<int, string>
     */
    public function optionChoicesForProduct(int $productId): array
    {
        $product = $this->product($productId);
        if ($product === null) {
            return [];
        }

        $choices = [];
        foreach ($this->options->storefrontOptionBlocks($product) as $block) {
            $groupName = trim((string) ($block['name'] ?? ''));
            foreach ($block['values'] ?? [] as $value) {
                $id = (int) ($value['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string) ($value['name'] ?? ''));
                $label = $groupName !== '' ? $groupName.' — '.$name : $name;
                $addon = (float) ($value['price'] ?? 0);
                if ($addon > 0.0001) {
                    $label .= ' (+'.number_format($addon, 0, '', ' ').' ₴)';
                }
                $choices[$id] = $label;
            }
        }

        return $choices;
    }

    /**
     * @param  list<int|string>|null  $optionValueIds
     */
    public function suggestedUnitPrice(int $productId, mixed $optionValueIds, int $qty = 1): ?float
    {
        $product = $this->product($productId);
        if ($product === null) {
            return null;
        }

        $selected = $this->options->normalizeOptionValueIds($optionValueIds ?? []);
        $resolved = $this->cartLines->resolveLine([
            'product_id' => $product->id,
            'qty' => max(1, $qty),
            'option_value_ids' => $selected,
        ], $product, requireSellable: false);

        if ($resolved === null) {
            return null;
        }

        return (float) $resolved['unit_price'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareDataForSave(Order $order, array $data, ?OrderItem $existing = null): array
    {
        if ($existing !== null && (int) ($existing->bundle_id ?? 0) > 0) {
            return $this->prepareBundleLine($data, $existing);
        }

        $productId = (int) ($data['product_id'] ?? ($existing?->product_id ?? 0));
        $product = $this->product($productId);
        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Оберіть товар з каталогу.',
            ]);
        }

        $selected = $this->options->normalizeOptionValueIds(
            $this->flattenOptionPicks($data['option_picks'] ?? null) !== []
                ? $this->flattenOptionPicks($data['option_picks'] ?? null)
                : ($data['option_value_ids'] ?? [])
        );
        $safe = $this->options->sanitizeSelectionForProduct($product, $selected);
        if ($selected !== $safe) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'У групах на кшталт «Розмір: …» можна обрати лише одну позицію.',
            ]);
        }
        $missing = $this->options->missingRequiredOptionGroupNames($product, $safe);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'option_value_ids' => 'Оберіть опції: '.implode(', ', $missing).'.',
            ]);
        }

        $qty = max(1, min(999, (int) ($data['qty'] ?? 1)));
        $resolved = $this->cartLines->resolveLine([
            'product_id' => $product->id,
            'qty' => $qty,
            'option_value_ids' => $safe,
        ], $product, requireSellable: false);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'product_id' => 'Не вдалося порахувати ціну для цього товару.',
            ]);
        }

        $unitPrice = $this->unitPriceFromForm($data['price'] ?? null, (float) $resolved['unit_price']);
        $title = (string) $product->title;
        $labels = $resolved['option_labels'] ?? [];
        if ($labels !== []) {
            $title .= ' · '.implode(', ', $labels);
        }

        $variant = $resolved['variant'] ?? null;

        return [
            'product_id' => $product->id,
            'variant_id' => $variant instanceof \App\Models\ProductVariant ? (int) $variant->id : null,
            'bundle_id' => null,
            'title_snapshot' => $title,
            'option_value_ids' => $resolved['option_value_ids'] ?? $safe,
            'bundle_snapshot' => null,
            'price' => $unitPrice,
            'qty' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
            'line_defers_online_payment' => $this->checkoutRules->lineDefersOnlinePayment($resolved),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareBundleLine(array $data, OrderItem $existing): array
    {
        $qty = max(1, min(999, (int) ($data['qty'] ?? $existing->qty)));
        $unitPrice = $this->unitPriceFromForm($data['price'] ?? null, (float) $existing->price);

        return [
            'qty' => $qty,
            'price' => $unitPrice,
            'line_total' => round($unitPrice * $qty, 2),
        ];
    }

    private function unitPriceFromForm(mixed $raw, float $catalogPrice): float
    {
        if ($raw === null || $raw === '') {
            return round($catalogPrice, 2);
        }

        return round(max(0, (float) $raw), 2);
    }

    /**
     * @return array{total: float, payment_status_changed: bool, payment_status: string}
     */
    public function recountTotals(Order $order): array
    {
        $order->loadMissing('items');
        $items = $order->items;
        $previousTotal = round((float) $order->total, 2);
        $previousStatus = (string) $order->payment_status;
        $total = round((float) $items->sum(fn (OrderItem $item): float => (float) $item->line_total), 2);
        $deferred = round((float) $items
            ->filter(fn (OrderItem $item): bool => (bool) $item->line_defers_online_payment)
            ->sum(fn (OrderItem $item): float => (float) $item->line_total), 2);
        $immediate = round(max(0, $total - $deferred), 2);

        $paymentStatus = $previousStatus;
        if ($previousStatus === 'paid' && $total > $previousTotal + 0.009) {
            $paymentStatus = 'partial';
        }

        $order->forceFill([
            'total' => $total,
            'immediate_subtotal' => $immediate,
            'deferred_subtotal' => $deferred,
            'deferred_online_payment' => $deferred > 0.0001,
            'mixed_payment_plan' => $immediate > 0.0001 && $deferred > 0.0001,
            'payment_status' => $paymentStatus,
        ])->save();

        return [
            'total' => $total,
            'payment_status_changed' => $paymentStatus !== $previousStatus,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * @return list<int>
     */
    public function flattenOptionPicks(mixed $picks): array
    {
        if (! is_array($picks)) {
            return [];
        }

        $ids = [];
        foreach ($picks as $value) {
            if (is_array($value)) {
                foreach ($value as $id) {
                    $n = (int) $id;
                    if ($n > 0) {
                        $ids[] = $n;
                    }
                }
            } else {
                $n = (int) $value;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
        }

        return $this->options->normalizeOptionValueIds($ids);
    }

    /**
     * @param  list<int>|null  $optionValueIds
     * @return array<int, int|list<int>>
     */
    public function optionPicksFromValueIds(int $productId, mixed $optionValueIds): array
    {
        $product = $this->product($productId);
        if ($product === null) {
            return [];
        }

        $selected = $this->options->normalizeOptionValueIds($optionValueIds ?? []);
        $picks = [];
        foreach ($this->options->storefrontOptionBlocks($product) as $block) {
            $gid = (int) ($block['id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $valueIds = [];
            foreach ($block['values'] ?? [] as $value) {
                $id = (int) ($value['id'] ?? 0);
                if ($id > 0 && in_array($id, $selected, true)) {
                    $valueIds[] = $id;
                }
            }
            if ($valueIds === []) {
                continue;
            }
            $picks[$gid] = ($block['selection_mode'] ?? 'single') === 'multiple'
                ? $valueIds
                : $valueIds[0];
        }

        return $picks;
    }

    /**
     * @return list<\Filament\Forms\Components\CheckboxList|\Filament\Forms\Components\Radio>
     */
    public function optionFormComponents(int $productId): array
    {
        $product = $this->product($productId);
        if ($product === null) {
            return [];
        }

        $components = [];
        foreach ($this->options->storefrontOptionBlocks($product) as $block) {
            $gid = (int) ($block['id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $choices = [];
            foreach ($block['values'] ?? [] as $value) {
                $id = (int) ($value['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string) ($value['name'] ?? ''));
                $label = $name !== '' ? $name : (string) $id;
                $addon = (float) ($value['price'] ?? 0);
                if ($addon > 0.0001) {
                    $label .= ' (+'.number_format($addon, 0, '', ' ').' ₴)';
                }
                $choices[$id] = $label;
            }
            if ($choices === []) {
                continue;
            }

            $fieldName = 'option_picks.'.$gid;
            $label = trim((string) ($block['name'] ?? 'Опції'));
            if (($block['selection_mode'] ?? 'single') === 'multiple') {
                $components[] = \Filament\Forms\Components\CheckboxList::make($fieldName)
                    ->label($label)
                    ->options($choices)
                    ->columns(1)
                    ->bulkToggleable(false)
                    ->live();
            } else {
                $components[] = \Filament\Forms\Components\Radio::make($fieldName)
                    ->label($label)
                    ->options($choices)
                    ->live();
            }
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareBundleCreate(array $data): array
    {
        $bundleId = (int) ($data['bundle_id'] ?? 0);
        $bundle = \App\Models\Bundle::query()->with(['items.product.variants'])->find($bundleId);
        if ($bundle === null) {
            throw ValidationException::withMessages([
                'bundle_id' => 'Оберіть комплект з каталогу.',
            ]);
        }

        $qty = max(1, min(999, (int) ($data['qty'] ?? 1)));
        $quote = app(BundlePricingService::class)->quote($bundle);
        $catalogPrice = round((float) ($quote['total'] ?? 0), 2);
        $unitPrice = $this->unitPriceFromForm($data['price'] ?? null, $catalogPrice);

        $defers = false;
        $snapshotItems = [];
        foreach ($bundle->items as $item) {
            $product = $item->product;
            $pid = (int) ($item->product_id ?? 0);
            if ($pid > 0 && $this->checkoutRules->productIdDefersOnlinePayment($pid)) {
                $defers = true;
            }
            $optionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];
            $variant = $product
                ? $this->options->resolveVariantForLine($product, $optionValueIds)
                : null;
            $snapshotItems[] = [
                'product_id' => $pid,
                'variant_id' => $variant?->id,
                'title' => (string) ($product?->title ?? ''),
                'option_value_ids' => $optionValueIds,
                'qty' => max(1, (int) $item->qty),
            ];
        }

        return [
            'product_id' => null,
            'bundle_id' => $bundle->id,
            'title_snapshot' => (string) $bundle->title,
            'option_value_ids' => null,
            'bundle_snapshot' => [
                'title' => $bundle->title,
                'sku' => $bundle->sku,
                'line_qty' => $qty,
                'line_total' => round($unitPrice * $qty, 2),
                'items' => $snapshotItems,
            ],
            'price' => $unitPrice,
            'qty' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
            'line_defers_online_payment' => $defers,
        ];
    }

    public function productTitleForTable(OrderItem $item): string
    {
        $title = trim((string) $item->title_snapshot);
        if ($title === '') {
            return '—';
        }

        $parts = preg_split('/\s[·\-]\s/u', $title, 2);

        return trim((string) ($parts[0] ?? $title));
    }

    private function product(int $productId): ?Product
    {
        if ($productId <= 0) {
            return null;
        }

        return Product::query()->find($productId);
    }
}
