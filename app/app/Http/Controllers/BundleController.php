<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Services\BundlePricingService;
use App\Services\CartDrawerService;
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
    ) {}

    public function index(): View
    {
        $bundles = Bundle::query()
            ->where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('title')
            ->get();

        $quotes = [];
        foreach ($bundles as $bundle) {
            $quotes[$bundle->id] = $this->bundlePricing->quote($bundle);
        }

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

        $itemRows = $bundle->items->map(function ($item): array {
            $product = $item->product;
            $lineQuote = $this->bundlePricing->lineQuote($item);

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
                'url' => $product ? route('catalog.show', $product->slug) : null,
                'photo' => $product ? $this->firstProductPhotoPath($product) : null,
                'excerpt' => $excerpt,
                'qty' => max(1, (int) $item->qty),
                'unit_price' => (float) $lineQuote['unit'],
                'line_total' => (float) $lineQuote['total'],
                'old_unit_price' => $oldUnitPrice !== null ? (float) $oldUnitPrice : null,
            ];
        })->values();

        $galleryPhotos = collect(is_array($bundle->photos ?? null) ? $bundle->photos : [])
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => ltrim($path, '/'))
            ->values();

        if ($galleryPhotos->isEmpty() && ($fallback = $bundle->firstCatalogPhotoPath())) {
            $galleryPhotos = collect([$fallback]);
        }

        $categoryBreadcrumb = $this->resolveCategoryBreadcrumb($bundle);
        $breadcrumbQueryBase = array_filter([
            'q' => $request->query('q'),
            'on_sale' => $request->boolean('on_sale') ? 1 : null,
        ], fn ($value) => $value !== null && $value !== '');

        return view('bundles.show', [
            'bundle' => $bundle,
            'quote' => $this->bundlePricing->quote($bundle),
            'bundleItems' => $itemRows,
            'galleryPhotos' => $galleryPhotos->all(),
            'categoryBreadcrumb' => $categoryBreadcrumb,
            'breadcrumbQueryBase' => $breadcrumbQueryBase,
        ]);
    }

    public function addToCart(Request $request, Bundle $bundle): RedirectResponse|JsonResponse
    {
        if (! $bundle->is_active || ! $bundle->is_visible) {
            abort(404);
        }

        $bundle->load(['items.product']);
        if ($bundle->items->isEmpty()) {
            return $this->errorResponse($request, 'У комплекті немає товарів для замовлення.');
        }

        $cart = ShopCart::normalize(is_array($request->session()->get('cart')) ? $request->session()->get('cart') : []);

        foreach ($bundle->items->sortBy('sort_order') as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_available) {
                return $this->errorResponse($request, 'Один з товарів комплекту недоступний.');
            }
        }

        $cartKey = $this->makeBundleCartKey((int) $bundle->id);
        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['qty'] = max(1, (int) ($cart[$cartKey]['qty'] ?? 1)) + 1;
        } else {
            $cart[$cartKey] = [
                'line_kind' => 'bundle',
                'bundle_id' => (int) $bundle->id,
                'qty' => 1,
                'option_value_ids' => [],
            ];
        }

        $request->session()->put('cart', $cart);

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
        return 'bundle:'.$bundleId;
    }

    private function firstProductPhotoPath($product): ?string
    {
        foreach (is_array($product->photos ?? null) ? $product->photos : [] as $path) {
            if (is_string($path) && $path !== '') {
                return ltrim($path, '/');
            }
        }

        foreach ($product->variants ?? [] as $variant) {
            foreach (is_array($variant->photos ?? null) ? $variant->photos : [] as $path) {
                if (is_string($path) && $path !== '') {
                    return ltrim($path, '/');
                }
            }
        }

        return null;
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
