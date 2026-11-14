<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\ProductVariant;
use App\Services\BundlePricingService;
use App\Services\ShopAnalytics;
use App\Services\BundleShowPageService;
use App\Services\CartDrawerService;
use App\Services\CartPersistence;
use App\Services\InventoryService;
use App\Services\ListingOptionSelectionService;
use App\Services\VariantPricingService;
use App\Support\ShopCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BundleController extends Controller
{
    public function __construct(
        private readonly BundlePricingService $bundlePricing,
        private readonly VariantPricingService $variantPricing,
        private readonly CartDrawerService $cartDrawer,
        private readonly BundleShowPageService $bundleShowPage,
        private readonly InventoryService $inventory,
        private readonly CartPersistence $cartPersistence,
    ) {}

    public function index(): View
    {
        $bundles = Bundle::query()
            ->where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('title')
            ->get();

        $quotes = $this->bundlePricing->quoteMany($bundles);

        return view('bundles.index', [
            'bundles' => $bundles,
            'quotes' => $quotes,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $bundle = Bundle::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->firstOrFail();

        $bundle->load(['items.product.variants']);
        ShopAnalytics::recordBundleView($request, (int) $bundle->id, (string) $bundle->title);

        $itemRows = $bundle->items->map(function ($item): array {
            $product = $item->product;
            $lineQuote = $this->bundlePricing->lineQuote($item);
            $optionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];

            $excerpt = '';
            if ($product) {
                $rawExcerpt = $product->short_description ?: $product->description;
                $excerpt = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $rawExcerpt))), 170);
            }

            $productQuote = $product ? $this->variantPricing->quoteProduct($product) : null;
            $oldUnitPrice = $productQuote?->strikePrice;

            return [
                'product' => $product,
                'title' => $product
                    ? (string) $product->title
                    : __('shop.bundles_line_missing_product', ['id' => $item->product_id]),
                'photo' => $item->displayPhotoPath(),
                'excerpt' => $excerpt,
                'qty' => max(1, (int) $item->qty),
                'option_value_ids' => $optionValueIds,
                'option_labels' => $product
                    ? app(ListingOptionSelectionService::class)->describeSelection($product, $optionValueIds)
                    : [],
                'unit_price' => (float) $lineQuote['unit'],
                'line_total' => (float) $lineQuote['total'],
                'old_unit_price' => $oldUnitPrice !== null ? (float) $oldUnitPrice : null,
            ];
        })->values();

        $galleryPhotos = $bundle->galleryPhotoPaths();

        $categoryBreadcrumb = $this->resolveCategoryBreadcrumb($bundle);
        $subcategoryFilterChip = $this->resolveSubcategoryFilterChip($request);
        $breadcrumbQueryBase = array_filter([
            'on_sale' => $request->boolean('on_sale') ? 1 : null,
            'category' => $request->integer('category') > 0 ? $request->integer('category') : null,
        ], fn ($value) => $value !== null && $value !== '');

        $bundleOptionsDisplay = $this->bundleShowPage->buildOptionDisplayBlocks($bundle);
        $quote = $this->bundlePricing->quote($bundle);

        return view('bundles.show', [
            'bundle' => $bundle,
            'quote' => $quote,
            'bundleItems' => $itemRows,
            'galleryPhotos' => $galleryPhotos,
            'categoryBreadcrumb' => $categoryBreadcrumb,
            'subcategoryFilterChip' => $subcategoryFilterChip,
            'breadcrumbQueryBase' => $breadcrumbQueryBase,
            'bundleOptionsDisplay' => $bundleOptionsDisplay,
            'bundleShowConfig' => [
                'baseSubtotal' => (float) ($quote['subtotal'] ?? 0),
                'baseDiscount' => (float) ($quote['discount'] ?? 0),
                'baseTotal' => (float) ($quote['total'] ?? 0),
                'optionBlocks' => $bundleOptionsDisplay,
            ],
        ]);
    }

    public function addToCart(Request $request, Bundle $bundle): RedirectResponse|JsonResponse
    {
        if (! $bundle->is_active || ! $bundle->is_visible) {
            abort(404);
        }

        $bundle->load(['items.product.variants']);
        if ($bundle->items->isEmpty()) {
            return $this->errorResponse($request, 'У комплекті немає товарів для замовлення.');
        }

        $cart = ShopCart::normalize(is_array($request->session()->get('cart')) ? $request->session()->get('cart') : []);

        foreach ($bundle->items->sortBy('sort_order') as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_available) {
                return $this->errorResponse($request, 'Один з товарів комплекту недоступний.');
            }
            if (! $this->bundleItemConfigurationIsSellable($item)) {
                return $this->errorResponse($request, 'Один з товарів комплекту недоступний у обраній конфігурації.');
            }
        }

        $selectedOptionValueIds = app(ListingOptionSelectionService::class)
            ->normalizeOptionValueIds($request->input('option_value_ids'));
        $safeSelection = $this->bundleShowPage->sanitizeSelectionForBundle($bundle, $selectedOptionValueIds);
        if ($safeSelection !== $selectedOptionValueIds) {
            return $this->errorResponse($request, 'Обрані опції комплекту недійсні.');
        }
        if (! $this->bundleShowPage->selectionIsCompleteForBundle($bundle, $safeSelection)) {
            if ($safeSelection === []) {
                if ($this->wantsCartFragment($request)) {
                    return $this->errorResponse($request, __('shop.card_bundle_choose_options'));
                }

                return redirect()
                    ->route('bundles.show', $bundle)
                    ->with('error', __('shop.card_bundle_choose_options'));
            }

            return $this->errorResponse($request, 'Оберіть усі опції комплекту.');
        }

        $cartKey = BundleShowPageService::makeCartKey((int) $bundle->id, $safeSelection);
        $nextQty = isset($cart[$cartKey])
            ? max(1, (int) ($cart[$cartKey]['qty'] ?? 1)) + 1
            : 1;
        $bundleError = $this->inventory->bundlePurchaseErrorForBundle($bundle, $nextQty);
        if ($bundleError !== null) {
            return $this->errorResponse($request, $bundleError);
        }

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['qty'] = max(1, (int) ($cart[$cartKey]['qty'] ?? 1)) + 1;
        } else {
            $cart[$cartKey] = [
                'line_kind' => 'bundle',
                'bundle_id' => (int) $bundle->id,
                'qty' => 1,
                'option_value_ids' => $safeSelection,
            ];
        }

        $request->session()->put('cart', $cart);
        $this->cartPersistence->persist($request, $request->user());
        ShopAnalytics::recordCart($request, (int) $bundle->id, (string) $bundle->title, 1);

        if ($this->wantsCartFragment($request)) {
            return $this->fragmentResponse(
                $this->cartDrawer->forRequest($request),
                'Комплект «'.$bundle->title.'» додано в кошик.'
            );
        }

        return redirect()->route('cart.index')->with('success', 'Комплект «'.$bundle->title.'» додано в кошик.');
    }

    private function makeBundleCartKey(int $bundleId): string
    {
        return BundleShowPageService::makeCartKey($bundleId, []);
    }

    private function bundleItemConfigurationIsSellable($item): bool
    {
        $product = $item->product;
        if (! $product) {
            return false;
        }

        $product->loadMissing('variants');
        $optionValueIds = is_array($item->option_value_ids ?? null) ? $item->option_value_ids : [];
        $variant = app(ListingOptionSelectionService::class)->resolveVariantForLine($product, $optionValueIds);
        $error = $this->inventory->purchaseError($product, $variant, max(1, (int) $item->qty));

        return $error === null;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function resolveCategoryBreadcrumb(Bundle $bundle): array
    {
        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId <= 0) {
            return [];
        }

        $categoryValues = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->where('is_active', true)
            ->get(['id', 'name', 'parent_id'])
            ->keyBy('id');

        if ($categoryValues->isEmpty()) {
            return [];
        }

        $startId = (int) ($bundle->category_value_id ?: $bundle->category_parent_value_id ?: 0);
        if ($startId <= 0 || ! $categoryValues->has($startId)) {
            return [];
        }

        $path = [];
        $currentId = $startId;
        $guard = 0;
        while ($guard < 16 && $currentId > 0 && $categoryValues->has($currentId)) {
            $node = $categoryValues->get($currentId);
            $name = trim((string) ($node->name ?? ''));
            if ($name !== '') {
                array_unshift($path, [
                    'id' => (int) ($node->id ?? 0),
                    'name' => $name,
                ]);
            }
            $currentId = (int) ($node->parent_id ?? 0);
            $guard++;
        }

        return $path;
    }

    /**
     * @return array{label: string, href: string}|null
     */
    private function resolveSubcategoryFilterChip(Request $request): ?array
    {
        $categoryId = (int) $request->integer('category');
        /** @var list<int> $selectedIds */
        $selectedIds = collect($request->input('subcategories', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($categoryId <= 0 || $selectedIds === []) {
            return null;
        }

        $categoryGroupId = OptionGroup::systemCategoryGroupIdForCatalog();
        if ($categoryGroupId <= 0) {
            return null;
        }

        $byId = OptionValue::query()
            ->where('option_group_id', $categoryGroupId)
            ->where('is_active', true)
            ->whereIn('id', $selectedIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        if ($byId->isEmpty()) {
            return null;
        }

        $labels = [];
        foreach ($selectedIds as $selectedId) {
            $node = $byId->get($selectedId);
            if ($node === null) {
                continue;
            }

            $name = trim((string) ($node->name ?? ''));
            if ($name !== '') {
                $labels[] = mt($name);
            }
        }

        if ($labels === []) {
            return null;
        }

        return [
            'label' => implode(', ', $labels).' ×',
            'href' => route('catalog.index', array_filter([
                'on_sale' => $request->boolean('on_sale') ? 1 : null,
                'category' => $categoryId,
            ], fn ($value) => $value !== null && $value !== '')),
        ];
    }

    private function wantsCartFragment(Request $request): bool
    {
        return $request->expectsJson() || $request->header('X-Cart-Fragment') === '1';
    }

    /**
     * @param  array{
     *     items: Collection<int, array<string, mixed>>,
     *     summary: array{lines_count: int, items_count: int, total: float, is_empty: bool}
     * }  $drawer
     */
    private function fragmentResponse(
        array $drawer,
        ?string $statusMessage = null,
        string $statusType = 'success',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'html' => view('cart.partials.drawer-content', [
                'items' => $drawer['items'],
                'summary' => $drawer['summary'],
                'statusMessage' => $statusMessage,
                'statusType' => $statusType,
            ])->render(),
            'summary' => $drawer['summary'],
            'statusMessage' => $statusMessage,
            'statusType' => $statusType,
        ], $status);
    }

    private function errorResponse(Request $request, string $message, int $status = 422): RedirectResponse|JsonResponse
    {
        if ($this->wantsCartFragment($request)) {
            return $this->fragmentResponse(
                $this->cartDrawer->forRequest($request),
                $message,
                'error',
                $status
            );
        }

        return back()->with('error', $message);
    }
}
