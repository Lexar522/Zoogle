<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Support\CatalogCategoryTree;
use App\Support\ShopCart;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Агрегує обмеження з кореневих категорій (option_values) для кошика / чекауту.
 */
class CategoryCheckoutRulesService
{
    public function __construct(
        private readonly CartDrawerService $cartDrawer,
    ) {}

    /**
     * @return array{requires_pickup_only: bool, defers_online_payment: bool}
     */
    public function aggregateForRequest(Request $request): array
    {
        if (! $request->hasSession()) {
            return $this->emptyRules();
        }

        $cart = ShopCart::normalize(is_array($request->session()->get('cart')) ? $request->session()->get('cart') : []);

        return $this->aggregateForCart($cart);
    }

    /**
     * @param  array<string, array{line_kind?: string, product_id?: int, bundle_id?: int, qty: int, option_value_ids: list<int>}>  $cart
     * @return array{requires_pickup_only: bool, defers_online_payment: bool}
     */
    public function aggregateForCart(array $cart): array
    {
        $productIds = $this->productIdsFromResolvedCart(
            $this->cartDrawer->fromCart($cart, true)['items'] ?? collect()
        );

        if ($productIds === []) {
            return $this->emptyRules();
        }

        return $this->aggregateForProductIds($productIds);
    }

    /**
     * @param  list<int>  $productIds
     * @return array{requires_pickup_only: bool, defers_online_payment: bool}
     */
    public function aggregateForProductIds(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds, fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return $this->emptyRules();
        }

        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0) {
            return $this->emptyRules();
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'category_value_id', 'category_parent_value_id', 'variant_options']);

        $rootIds = [];
        foreach ($products as $p) {
            $nodeId = $p->resolvedCatalogCategoryNodeId($groupId);
            if ($nodeId <= 0) {
                continue;
            }
            $root = CatalogCategoryTree::rootIdFromNode($nodeId, $groupId);
            if ($root > 0) {
                $rootIds[$root] = true;
            }
        }

        if ($rootIds === []) {
            return $this->emptyRules();
        }

        $flags = OptionValue::query()
            ->whereIn('id', array_keys($rootIds))
            ->get(['id', 'pickup_only_subtree', 'defer_online_payment']);

        $requiresPickup = false;
        $defers = false;
        foreach ($flags as $row) {
            if ($row->pickup_only_subtree) {
                $requiresPickup = true;
            }
            if ($row->defer_online_payment) {
                $defers = true;
            }
        }

        return [
            'requires_pickup_only' => $requiresPickup,
            'defers_online_payment' => $defers,
        ];
    }

    /**
     * Чи корінь категорії товару має відкладену онлайн-оплату.
     */
    public function deferOnlinePaymentForProductId(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($groupId <= 0) {
            return false;
        }

        $product = Product::query()
            ->whereKey($productId)
            ->first(['id', 'category_value_id', 'category_parent_value_id', 'variant_options']);

        if ($product === null) {
            return false;
        }

        $nodeId = $product->resolvedCatalogCategoryNodeId($groupId);
        if ($nodeId <= 0) {
            return false;
        }

        $root = CatalogCategoryTree::rootIdFromNode($nodeId, $groupId);
        if ($root <= 0) {
            return false;
        }

        return (bool) OptionValue::query()
            ->whereKey($root)
            ->value('defer_online_payment');
    }

    /**
     * Розбиття суми кошика: позиції з відкладеною категорією vs решта (аксесуари тощо).
     *
     * @param  Collection<int, array<string, mixed>>  $resolvedLines
     * @return array{
     *     immediate_subtotal: float,
     *     deferred_subtotal: float,
     *     requires_pickup_only: bool,
     *     defers_any: bool,
     *     is_mixed: bool,
     * }
     */
    public function paymentSplitForResolvedCartLines(Collection $resolvedLines): array
    {
        $immediate = 0.0;
        $deferred = 0.0;

        foreach ($resolvedLines as $line) {
            $kind = (string) ($line['line_kind'] ?? 'product');
            $lineTotal = round((float) ($line['line_total'] ?? 0), 2);

            if ($kind === 'bundle') {
                $bundleItems = $line['bundle_items'] ?? [];
                $defers = false;
                if (is_array($bundleItems)) {
                    foreach ($bundleItems as $bi) {
                        $pid = (int) ($bi['product_id'] ?? 0);
                        if ($pid > 0 && $this->deferOnlinePaymentForProductId($pid)) {
                            $defers = true;
                            break;
                        }
                    }
                }
                if ($defers) {
                    $deferred += $lineTotal;
                } else {
                    $immediate += $lineTotal;
                }

                continue;
            }

            /** @var Product|null $product */
            $product = $line['product'] ?? null;
            $pid = $product instanceof Product ? (int) $product->id : 0;
            if ($pid <= 0) {
                $immediate += $lineTotal;

                continue;
            }

            if ($this->deferOnlinePaymentForProductId($pid)) {
                $deferred += $lineTotal;
            } else {
                $immediate += $lineTotal;
            }
        }

        $pickIds = $this->productIdsFromResolvedCart($resolvedLines);
        $pickRules = $pickIds === [] ? $this->emptyRules() : $this->aggregateForProductIds($pickIds);

        $immediate = round($immediate, 2);
        $deferred = round($deferred, 2);
        $defersAny = $deferred > 0.00001;
        $isMixed = $immediate > 0.00001 && $deferred > 0.00001;

        return [
            'immediate_subtotal' => $immediate,
            'deferred_subtotal' => $deferred,
            'requires_pickup_only' => $pickRules['requires_pickup_only'],
            'defers_any' => $defersAny,
            'is_mixed' => $isMixed,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return list<int>
     */
    private function productIdsFromResolvedCart(Collection $items): array
    {
        $out = [];
        foreach ($items as $line) {
            $kind = (string) ($line['line_kind'] ?? 'product');
            if ($kind === 'product' && ! empty($line['product'])) {
                $p = $line['product'];
                if (is_object($p) && isset($p->id)) {
                    $out[] = (int) $p->id;
                }

                continue;
            }
            if ($kind === 'bundle' && ! empty($line['bundle_items']) && is_array($line['bundle_items'])) {
                foreach ($line['bundle_items'] as $bi) {
                    $pid = (int) ($bi['product_id'] ?? 0);
                    if ($pid > 0) {
                        $out[] = $pid;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return array{requires_pickup_only: bool, defers_online_payment: bool}
     */
    private function emptyRules(): array
    {
        return [
            'requires_pickup_only' => false,
            'defers_online_payment' => false,
        ];
    }
}
