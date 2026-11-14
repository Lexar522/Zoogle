<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartDrawerService;
use App\Services\ShopAnalytics;
use App\Services\CartPersistence;
use App\Services\InventoryService;
use App\Services\ListingOptionSelectionService;
use App\Support\ShopCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly ListingOptionSelectionService $listingOptionSelection,
        private readonly CartDrawerService $cartDrawer,
        private readonly InventoryService $inventory,
        private readonly CartPersistence $cartPersistence,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $drawer = $this->cartDrawer->forRequest($request);

        if ($this->wantsCartFragment($request)) {
            return $this->fragmentResponse($drawer);
        }

        return view('cart.index', [
            'items' => $drawer['items'],
            'summary' => $drawer['summary'],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'option_value_ids' => ['nullable', 'string'],
            'variant_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::query()->with('variants')->findOrFail($validated['product_id']);

        if (! $product->is_available) {
            return $this->errorResponse($request, 'Цей товар зараз прихований з каталогу.');
        }
        if (! $product->isOrderable()) {
            return $this->errorResponse($request, __('shop.cart_product_out_of_stock'));
        }

        $selectedOptionValueIds = $this->listingOptionSelection->normalizeOptionValueIds($request->input('option_value_ids'));
        $safeSelection = $this->listingOptionSelection->sanitizeSelectionForProduct($product, $selectedOptionValueIds);
        if ($safeSelection !== $selectedOptionValueIds) {
            return $this->errorResponse($request, 'Обрані опції товару недійсні.');
        }

        $missingGroups = $this->listingOptionSelection->missingRequiredOptionGroupNames($product, $safeSelection);
        if ($missingGroups !== []) {
            return $this->errorResponse(
                $request,
                __('shop.pdp_select_options_before_cart', ['groups' => implode(', ', $missingGroups)]),
            );
        }

        $cart = $this->getCart($request);
        $requestedVariantId = (int) ($validated['variant_id'] ?? 0);
        $lineOptionSets = $this->listingOptionSelection->splitIntoCartLineOptionSets($product, $safeSelection);
        foreach ($lineOptionSets as $lineOptionValueIds) {
            $variant = $this->listingOptionSelection->resolveVariantForLine($product, $lineOptionValueIds);
            if ($requestedVariantId > 0 && ($variant === null || (int) $variant->id !== $requestedVariantId)) {
                $picked = $product->variants->first(
                    fn (ProductVariant $candidate): bool => (int) $candidate->id === $requestedVariantId
                        && $candidate->isVisibleOnStorefront()
                );
                if ($picked instanceof ProductVariant && $lineOptionValueIds === []) {
                    $variant = $picked;
                }
            }
            if ($variant === null) {
                $hasVisibleVariant = $product->variants->contains(
                    fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()
                );
                if ($hasVisibleVariant) {
                    return $this->errorResponse($request, 'Обрана комбінація опцій недоступна для цього товару.');
                }
            }

            $alreadyInCart = $this->qtyAlreadyInCartForVariant($cart, (int) $product->id, $variant);
            $error = $this->inventory->purchaseError($product, $variant, 1, $alreadyInCart);
            if ($error !== null) {
                return $this->errorResponse($request, $error);
            }

            $cartKey = $this->makeCartKey((int) $product->id, $lineOptionValueIds, $variant?->id);
            if (isset($cart[$cartKey])) {
                $cart[$cartKey]['qty'] = max(1, (int) ($cart[$cartKey]['qty'] ?? 1)) + 1;
            } else {
                $cart[$cartKey] = [
                    'product_id' => (int) $product->id,
                    'qty' => 1,
                    'option_value_ids' => $lineOptionValueIds,
                    'variant_id' => $variant?->id,
                ];
            }
        }
        $request->session()->put('cart', $cart);
        $this->cartPersistence->persist($request, $request->user());
        ShopAnalytics::recordCart($request, (int) $product->id, (string) $product->title, count($lineOptionSets));

        if ($this->wantsCartFragment($request)) {
            return $this->fragmentResponse(
                $this->cartDrawer->forRequest($request),
                'Позицію додано в кошик.'
            );
        }

        return redirect()->route('cart.index')->with('success', 'Позицію додано в кошик.');
    }

    public function update(Request $request, string $cartKey): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cart = $this->getCart($request);
        if (! isset($cart[$cartKey])) {
            return $this->errorResponse($request, 'Позицію не знайдено.', 404);
        }

        $row = $cart[$cartKey];
        if (($row['line_kind'] ?? null) === 'bundle') {
            return $this->updateBundleLine($request, $cart, $cartKey, (int) $validated['qty']);
        }

        $product = Product::query()->with('variants')->find((int) ($row['product_id'] ?? 0));
        if ($product === null || ! $product->is_available) {
            return $this->errorResponse($request, 'Цей товар зараз прихований з каталогу.');
        }
        if (! $product->isOrderable()) {
            return $this->errorResponse($request, __('shop.cart_product_out_of_stock'));
        }

        $rawSelection = is_array($row['option_value_ids'] ?? null) ? $row['option_value_ids'] : [];
        $lineSelection = $this->listingOptionSelection->sanitizeSelectionForProduct($product, $rawSelection);
        $normalizedRaw = $this->listingOptionSelection->normalizeOptionValueIds($rawSelection);
        if ($lineSelection !== $normalizedRaw) {
            unset($cart[$cartKey]);
            $request->session()->put('cart', $cart);

            return $this->errorResponse($request, 'Обрані опції товару недійсні.');
        }

        $variant = $this->listingOptionSelection->resolveVariantForLine($product, $lineSelection);
        $requestedVariantId = (int) ($row['variant_id'] ?? 0);
        if ($lineSelection === [] && $requestedVariantId > 0) {
            $picked = $product->variants->first(
                fn (ProductVariant $candidate): bool => (int) $candidate->id === $requestedVariantId
                    && $candidate->isVisibleOnStorefront()
            );
            if ($picked instanceof ProductVariant) {
                $variant = $picked;
            }
        }
        $alreadyInCart = $this->qtyAlreadyInCartForVariant($cart, (int) $product->id, $variant, $cartKey);
        $error = $this->inventory->purchaseError($product, $variant, (int) $validated['qty'], $alreadyInCart);
        if ($error !== null) {
            return $this->errorResponse($request, $error);
        }

        $cart[$cartKey]['qty'] = (int) $validated['qty'];
        $cart[$cartKey]['option_value_ids'] = $lineSelection;
        $cart[$cartKey]['variant_id'] = $variant?->id;

        return $this->saveUpdatedQuantity($request, $cart);
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    private function updateBundleLine(Request $request, array $cart, string $cartKey, int $qty): RedirectResponse|JsonResponse
    {
        $bundle = Bundle::query()
            ->with('items.product.variants')
            ->find((int) ($cart[$cartKey]['bundle_id'] ?? 0));
        if ($bundle === null || ! $bundle->is_active || ! $bundle->is_visible) {
            return $this->errorResponse($request, 'Цей комплект зараз недоступний.');
        }

        $error = $this->inventory->bundlePurchaseErrorForBundle($bundle, $qty);
        if ($error !== null) {
            return $this->errorResponse($request, $error);
        }

        $cart[$cartKey]['qty'] = $qty;

        return $this->saveUpdatedQuantity($request, $cart);
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    private function saveUpdatedQuantity(Request $request, array $cart): RedirectResponse|JsonResponse
    {
        $request->session()->put('cart', $cart);
        $this->cartPersistence->persist($request, $request->user());

        if ($this->wantsCartFragment($request)) {
            return $this->fragmentResponse(
                $this->cartDrawer->forRequest($request),
                'Кількість оновлено.'
            );
        }

        return redirect()->route('cart.index')->with('success', 'Кількість оновлено.');
    }

    public function destroy(Request $request, string $cartKey): RedirectResponse|JsonResponse
    {
        $cart = $this->getCart($request);
        if (! isset($cart[$cartKey])) {
            return $this->errorResponse($request, 'Позицію не знайдено.', 404);
        }

        unset($cart[$cartKey]);
        $request->session()->put('cart', $cart);
        $this->cartPersistence->persist($request, $request->user());

        if ($this->wantsCartFragment($request)) {
            return $this->fragmentResponse(
                $this->cartDrawer->forRequest($request),
                'Позицію видалено з кошика.'
            );
        }

        return redirect()->route('cart.index')->with('success', 'Позицію видалено з кошика.');
    }

    /**
     * @return array<string, array{product_id: int, qty: int, option_value_ids: list<int>}>
     */
    private function getCart(Request $request): array
    {
        $cart = $request->session()->get('cart', []);

        return ShopCart::normalize(is_array($cart) ? $cart : []);
    }

    /**
     * @param  list<int>  $optionValueIds
     */
    private function makeCartKey(int $productId, array $optionValueIds, ?int $variantId = null): string
    {
        $optionsPart = $optionValueIds === [] ? 'none' : implode(',', $optionValueIds);
        if ($optionValueIds === [] && $variantId !== null && $variantId > 0) {
            return $productId.':v'.$variantId;
        }

        return $productId.':'.$optionsPart;
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    private function qtyAlreadyInCartForVariant(
        array $cart,
        int $productId,
        ?ProductVariant $variant,
        ?string $exceptKey = null
    ): int {
        $qty = 0;
        foreach ($cart as $key => $row) {
            if ($exceptKey !== null && (string) $key === $exceptKey) {
                continue;
            }
            if ((int) ($row['product_id'] ?? 0) !== $productId) {
                continue;
            }
            $rowVariantId = (int) ($row['variant_id'] ?? 0);
            if ($variant === null) {
                if ($rowVariantId <= 0) {
                    $qty += max(1, (int) ($row['qty'] ?? 1));
                }

                continue;
            }
            if ($rowVariantId === (int) $variant->id) {
                $qty += max(1, (int) ($row['qty'] ?? 1));
            }
        }

        return $qty;
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
