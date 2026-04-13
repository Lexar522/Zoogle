<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartDrawerService;
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
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        if (! $product->is_available) {
            return $this->errorResponse($request, 'Цей товар зараз прихований з каталогу.');
        }

        $selectedOptionValueIds = $this->listingOptionSelection->normalizeOptionValueIds($request->input('option_value_ids'));
        $safeSelection = $this->listingOptionSelection->sanitizeSelectionForProduct($product, $selectedOptionValueIds);
        if ($safeSelection !== $selectedOptionValueIds) {
            return $this->errorResponse($request, 'Обрані опції товару недійсні.');
        }

        $cart = $this->getCart($request);
        $lineOptionSets = $this->listingOptionSelection->splitIntoCartLineOptionSets($product, $safeSelection);
        foreach ($lineOptionSets as $lineOptionValueIds) {
            $variant = $this->listingOptionSelection->resolveVariantForLine($product, $lineOptionValueIds);
            if ($variant === null) {
                $product->loadMissing(['variants']);
                $hasVisibleVariant = $product->variants->contains(
                    fn (ProductVariant $v): bool => $v->isVisibleOnStorefront()
                );
                if ($hasVisibleVariant) {
                    return $this->errorResponse($request, 'Обрана комбінація опцій недоступна для цього товару.');
                }
            }
            $cartKey = $this->makeCartKey((int) $product->id, $lineOptionValueIds);
            if (isset($cart[$cartKey])) {
                $cart[$cartKey]['qty'] = max(1, (int) ($cart[$cartKey]['qty'] ?? 1)) + 1;
            } else {
                $cart[$cartKey] = [
                    'product_id' => (int) $product->id,
                    'qty' => 1,
                    'option_value_ids' => $lineOptionValueIds,
                ];
            }
        }
        $request->session()->put('cart', $cart);

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

        $cart[$cartKey]['qty'] = (int) $validated['qty'];
        $request->session()->put('cart', $cart);

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
    private function makeCartKey(int $productId, array $optionValueIds): string
    {
        $optionsPart = $optionValueIds === [] ? 'none' : implode(',', $optionValueIds);

        return $productId.':'.$optionsPart;
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
