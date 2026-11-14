<?php

namespace App\Http\Controllers;

use App\Mail\OrderPlaced;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopIntegrationSetting;
use App\Services\CartDrawerService;
use App\Services\ShopAnalytics;
use App\Services\CategoryCheckoutRulesService;
use App\Services\CartPersistence;
use App\Services\InventoryService;
use App\Services\NovaPoshta\NovaPoshtaClient;
use App\Services\Payments\ApplyApprovedOrderPayment;
use App\Services\Payments\ApplyUnsuccessfulOrderPayment;
use App\Services\Payments\LiqPayClient;
use App\Services\Payments\WayForPayClient;
use App\Support\GoogleMapsApiKey;
use App\Support\GuestOrderAccess;
use App\Support\OnlinePaymentSettings;
use App\Support\ShopCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const SUBMIT_TTL_SECONDS = 600;

    public function __construct(
        private readonly CartDrawerService $cartDrawer,
        private readonly CategoryCheckoutRulesService $categoryCheckoutRules,
        private readonly InventoryService $inventory,
        private readonly CartPersistence $cartPersistence,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->getCart($request);
        if ($cart === []) {
            return redirect()->route('catalog.index')->with('error', 'Кошик порожній.');
        }

        $drawer = $this->cartDrawer->fromCart($cart, forCheckout: true);
        $items = $drawer['items'];

        if ($items->isEmpty()) {
            return redirect()->route('catalog.index')->with('error', 'У кошику немає доступних позицій.');
        }

        if (($drawer['summary']['can_checkout'] ?? true) !== true) {
            return redirect()->route('cart.index')->with(
                'error',
                (string) ($drawer['summary']['blocked_reason'] ?: __('shop.cart_checkout_blocked'))
            );
        }

        if ($this->hasZeroPriceLine($items)) {
            return redirect()->route('cart.index')->with('error', __('shop.checkout_zero_price'));
        }

        $user = Auth::user();

        $shop = ShopIntegrationSetting::record();
        $pickupEnabled = $shop->isPickupEnabled();
        $categoryRules = $this->categoryCheckoutRules->aggregateForRequest($request);
        $paymentSplit = $this->categoryCheckoutRules->paymentSplitForResolvedCartLines($items);

        if ($categoryRules['requires_pickup_only'] && ! $pickupEnabled) {
            return redirect()->route('catalog.index')->with(
                'error',
                __('shop.checkout_pickup_disabled_for_category'),
            );
        }

        ShopAnalytics::recordCheckout($request);

        return view('checkout.index', [
            'items' => $items,
            'total' => $items->sum('line_total'),
            'novaPoshtaConfigured' => app(NovaPoshtaClient::class)->isConfigured(),
            'liqPayConfigured' => app(OnlinePaymentSettings::class)->isConfigured(),
            'checkoutGoogleMapsKey' => app(GoogleMapsApiKey::class)->current(),
            'categoryRequiresPickupOnly' => $categoryRules['requires_pickup_only'],
            'categoryDefersOnlinePayment' => (bool) ($paymentSplit['defers_any'] ?? false),
            'pickupEnabled' => $pickupEnabled,
            'paymentSplit' => $paymentSplit,
            'pickupDisplay' => [
                'address' => $shop->pickup_address,
                'lat' => $shop->pickup_lat,
                'lng' => $shop->pickup_lng,
            ],
            'checkoutPrefill' => [
                'customer_name' => $user !== null ? $user->checkoutDisplayName() : '',
                'customer_email' => $user?->email ?? '',
                'customer_phone' => $user?->phone ? trim((string) $user->phone) : '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $submitKey = $this->submitCacheKey($request);
        if ($submitKey !== null && Cache::has($submitKey)) {
            return $this->responseForDuplicateSubmit($submitKey);
        }

        $cart = $this->getCart($request);
        if ($cart === []) {
            return redirect()->route('catalog.index')->with('error', 'Кошик порожній.');
        }

        $categoryRules = $this->categoryCheckoutRules->aggregateForCart($cart);
        $pickupEnabled = ShopIntegrationSetting::record()->isPickupEnabled();
        $npConfigured = app(NovaPoshtaClient::class)->isConfigured();
        $liqPayConfigured = app(OnlinePaymentSettings::class)->isConfigured();
        $deliveryType = $request->string('delivery_type')->toString();

        $drawer = $this->cartDrawer->fromCart($cart, forCheckout: true);
        $items = $drawer['items'];
        if ($items->isEmpty()) {
            return redirect()->route('catalog.index')->with('error', 'У кошику немає позицій для оформлення.');
        }
        if (($drawer['summary']['can_checkout'] ?? true) !== true) {
            return redirect()->route('cart.index')->with(
                'error',
                (string) ($drawer['summary']['blocked_reason'] ?: __('shop.cart_checkout_blocked'))
            );
        }
        if ($this->hasZeroPriceLine($items)) {
            return redirect()->route('cart.index')->with('error', __('shop.checkout_zero_price'));
        }

        $paymentSplit = $this->categoryCheckoutRules->paymentSplitForResolvedCartLines($items);
        $defersAny = $paymentSplit['defers_any'];
        $isMixed = $paymentSplit['is_mixed'];

        if (! $liqPayConfigured) {
            $allowedPaymentMethods = ['cod'];
        } elseif ($defersAny && ! $isMixed) {
            $allowedPaymentMethods = ['cod'];
        } else {
            $allowedPaymentMethods = ['cod', 'online'];
        }
        $allowedDeliveryTypes = $this->allowedDeliveryTypesForCheckout(
            $categoryRules['requires_pickup_only'],
            $pickupEnabled,
        );

        if ($allowedDeliveryTypes === []) {
            return redirect()->route('catalog.index')->with(
                'error',
                __('shop.checkout_pickup_disabled_for_category'),
            );
        }

        $rules = [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^(?=(?:\D*\d){9,15}\D*$)\+?[\d\s()\-]+$/'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'payment_method' => [
                'required',
                'string',
                Rule::in($allowedPaymentMethods),
            ],
            'delivery_type' => ['required', 'string', Rule::in($allowedDeliveryTypes)],
            'delivery_city' => ['nullable', 'string', 'max:120'],
            'delivery_city_ref' => ['nullable', 'string', 'max:36'],
            'delivery_branch' => ['nullable', 'string', 'max:255'],
            'delivery_warehouse_ref' => ['nullable', 'string', 'max:36'],
            'delivery_street' => ['nullable', 'string', 'max:255'],
            'delivery_street_ref' => ['nullable', 'string', 'max:36'],
            'delivery_building' => ['nullable', 'string', 'max:32'],
            'delivery_flat' => ['nullable', 'string', 'max:32'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($deliveryType === Order::DELIVERY_NOVA_POSHTA_WAREHOUSE) {
            $rules['delivery_city'] = ['required', 'string', 'max:120'];
            $rules['delivery_city_ref'] = $npConfigured
                ? ['required', 'string', 'size:36']
                : ['nullable', 'string', 'max:36'];
            $rules['delivery_branch'] = ['required', 'string', 'max:255'];
            $rules['delivery_warehouse_ref'] = $npConfigured
                ? ['required', 'string', 'size:36']
                : ['nullable', 'string', 'max:36'];
        }

        if ($deliveryType === Order::DELIVERY_NOVA_POSHTA_COURIER) {
            $rules['delivery_address'] = ['required', 'string', 'max:500'];
            $rules['delivery_city'] = ['required', 'string', 'max:120'];
            $rules['delivery_city_ref'] = $npConfigured
                ? ['required', 'string', 'size:36']
                : ['nullable', 'string', 'max:36'];
        }

        $validated = $request->validate($rules, [
            'customer_phone.regex' => __('shop.checkout_phone_invalid'),
        ]);
        $paymentMethod = (string) ($validated['payment_method'] ?? 'cod');
        unset($validated['payment_method']);
        $validated = $this->normalizeDeliveryPayload($validated);

        $total = $items->sum('line_total');

        if ($submitKey !== null && ! Cache::add($submitKey, 'pending', self::SUBMIT_TTL_SECONDS)) {
            return $this->responseForDuplicateSubmit($submitKey);
        }

        try {
            $order = $this->placeOrder($items, $total, $validated, $request, $paymentSplit, $paymentMethod, $defersAny);
        } catch (\Throwable $e) {
            if ($submitKey !== null) {
                Cache::forget($submitKey);
            }

            throw $e;
        }

        $this->cartPersistence->clear($request, $request->user());

        if ($paymentMethod === 'online' && app(OnlinePaymentSettings::class)->isConfigured()) {
            $pay = app(OnlinePaymentSettings::class);
            $order->forceFill([
                'payment_provider' => $pay->provider() === OnlinePaymentSettings::PROVIDER_WAYFORPAY ? 'wayforpay' : 'liqpay',
            ])->save();
        }

        if ($submitKey !== null) {
            Cache::put($submitKey, $order->id, self::SUBMIT_TTL_SECONDS);
        }

        return $this->redirectAfterPlacement($order);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function hasZeroPriceLine(Collection $items): bool
    {
        return $items->contains(fn (array $item): bool => (float) ($item['line_total'] ?? 0) <= 0);
    }

    /** Ключ одноразового токена форми: повторна відправка не створює другого замовлення. */
    private function submitCacheKey(Request $request): ?string
    {
        $token = (string) $request->input('checkout_token', '');
        if ($token === '' || strlen($token) > 64) {
            return null;
        }

        return 'checkout-submit:'.hash('sha256', $token);
    }

    private function responseForDuplicateSubmit(string $submitKey): RedirectResponse
    {
        // Перший запит ще може створювати замовлення: чекаємо до 5 секунд.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $orderId = Cache::get($submitKey);
            if (is_numeric($orderId)) {
                $order = Order::query()->find((int) $orderId);
                if ($order !== null) {
                    return $this->redirectAfterPlacement($order);
                }
            }
            if ($orderId === null) {
                break;
            }
            usleep(500_000);
        }

        return redirect()->route('cart.index')->with('error', __('shop.checkout_already_processing'));
    }

    private function redirectAfterPlacement(Order $order): RedirectResponse
    {
        if ($order->checkout_payment_method === 'online' && app(OnlinePaymentSettings::class)->isConfigured()) {
            if ($order->mixed_payment_plan) {
                return redirect()->route('checkout.payment', [
                    'order' => $order->id,
                    'token' => $order->success_token,
                    'leg' => 'immediate',
                ]);
            }

            if (! $order->deferred_online_payment) {
                return redirect()->route('checkout.payment', [
                    'order' => $order->id,
                    'token' => $order->success_token,
                ]);
            }
        }

        return redirect()->route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $paymentSplit
     */
    private function placeOrder(Collection $items, float|int $total, array $validated, Request $request, array $paymentSplit, string $paymentMethod, bool $defersAny): Order
    {
        return DB::transaction(function () use ($items, $total, $validated, $request, $paymentSplit, $paymentMethod, $defersAny): Order {
            $orderPayload = [
                ...$validated,
                'total' => $total,
                'status' => 'new',
                'payment_status' => 'pending',
                'deferred_online_payment' => $defersAny,
                'mixed_payment_plan' => $paymentSplit['is_mixed'],
                'immediate_subtotal' => $paymentSplit['immediate_subtotal'],
                'deferred_subtotal' => $paymentSplit['deferred_subtotal'],
                'checkout_payment_method' => $paymentMethod,
            ];
            if ($request->user() !== null) {
                $orderPayload['user_id'] = $request->user()->id;
            }

            $order = Order::query()->create($orderPayload);

            $this->inventory->commitForOrder($order, $items);

            foreach ($items as $item) {
                $qty = (int) $item['qty'];

                if (($item['line_kind'] ?? 'product') === 'bundle') {
                    /** @var Bundle|null $bundle */
                    $bundle = $item['bundle'] ?? null;
                    $bundleSnapshot = is_array($item['bundle_snapshot'] ?? null)
                        ? array_merge($item['bundle_snapshot'], [
                            'line_qty' => $qty,
                            'line_total' => round((float) ($item['line_total'] ?? 0), 2),
                        ])
                        : null;

                    $order->items()->create([
                        'product_id' => null,
                        'bundle_id' => $bundle?->id,
                        'title_snapshot' => (string) ($item['title'] ?? ($bundle?->title ?? 'Комплект')),
                        'option_value_ids' => is_array($item['option_value_ids'] ?? null) ? $item['option_value_ids'] : null,
                        'bundle_snapshot' => $bundleSnapshot,
                        'price' => $item['unit_price'],
                        'qty' => $qty,
                        'line_total' => $item['line_total'],
                        'line_defers_online_payment' => $this->lineDefersOnlinePayment($item),
                    ]);

                    continue;
                }

                /** @var Product $product */
                $product = $item['product'];

                $titleSnapshot = $product->title;
                if (! empty($item['option_labels'] ?? [])) {
                    $titleSnapshot .= ' · '.implode(', ', $item['option_labels']);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $item['variant'] instanceof \App\Models\ProductVariant
                        ? (int) $item['variant']->id
                        : null,
                    'title_snapshot' => $titleSnapshot,
                    'option_value_ids' => $item['option_value_ids'] ?? [],
                    'price' => $item['unit_price'],
                    'qty' => $qty,
                    'line_total' => $item['line_total'],
                    'line_defers_online_payment' => $this->lineDefersOnlinePayment($item),
                ]);
            }

            DB::afterCommit(function () use ($order): void {
                $email = trim((string) $order->customer_email);
                if ($email === '') {
                    return;
                }

                // Замовлення вже збережене: збій пошти не має показувати покупцю помилку і провокувати дубль.
                try {
                    Mail::to($email)->send(new OrderPlaced($order->fresh(['items'])));
                } catch (\Throwable $e) {
                    Log::warning('order_placed_email_failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            return $order;
        });
    }

    public function payment(Request $request, Order $order, ?string $token = null): View|RedirectResponse
    {
        $token = $token !== null && $token !== '' ? $token : (string) $request->query('token', '');
        if (! $order->successTokenMatches($token)) {
            abort(404);
        }

        $paySettings = app(OnlinePaymentSettings::class);
        if (! $paySettings->isConfigured()) {
            return redirect()->route('checkout.success', ['order' => $order->id, 'token' => $token]);
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('checkout.success', ['order' => $order->id, 'token' => $token]);
        }

        if ($order->status === Order::STATUS_CANCELLED) {
            return redirect()
                ->route('checkout.success', ['order' => $order->id, 'token' => $token])
                ->with('error', __('shop.checkout_order_cancelled_no_payment'));
        }

        $leg = $this->resolveCheckoutPaymentLeg($order, $request);

        if ($leg === 'full' && $order->mixed_payment_plan) {
            return redirect()
                ->route('checkout.success', ['order' => $order->id, 'token' => $token])
                ->with('error', 'Для цього замовлення оберіть відповідну частину оплати (посилання з листа або з кабінету).');
        }

        if ($leg === 'deferred') {
            if (! $order->canPayDeferredLiqPay()) {
                return redirect()
                    ->route('checkout.success', ['order' => $order->id, 'token' => $token])
                    ->with('error', 'Онлайн-оплата цієї частини ще недоступна. Якщо чекаєте після підтвердження менеджера — оновіть сторінку пізніше.');
            }
        } elseif ($leg === 'immediate') {
            if (! $order->canPayImmediateLiqPay()) {
                return redirect()
                    ->route('checkout.success', ['order' => $order->id, 'token' => $token])
                    ->with('error', 'Перша частина оплати вже сплачена або недоступна.');
            }
        } else {
            if ($order->deferred_online_payment && $order->online_payment_unlocked_at === null) {
                return redirect()
                    ->route('checkout.success', ['order' => $order->id, 'token' => $token])
                    ->with('error', 'Онлайн-оплата для цього замовлення ще не дозволена. Очікуйте повідомлення від магазину.');
            }
        }

        if (! $order->prepareForPaymentRetry()) {
            return redirect()
                ->route('checkout.success', ['order' => $order->id, 'token' => $token])
                ->with('error', __('shop.checkout_retry_out_of_stock'));
        }

        if ($paySettings->provider() === OnlinePaymentSettings::PROVIDER_WAYFORPAY) {
            $wfp = app(WayForPayClient::class);
            $form = $wfp->purchaseFormFields($order, $leg === 'full' ? 'full' : ($leg === 'immediate' ? 'immediate' : 'deferred'));
            $orderReference = (string) ($form['fields']['orderReference'] ?? '');
            if ($orderReference !== '') {
                $order->forceFill([
                    'payment_provider' => 'wayforpay',
                    'payment_external_id' => $orderReference,
                ])->save();
            }

            return view('checkout.payment-wayforpay', [
                'action' => $form['action'],
                'fields' => $form['fields'],
            ]);
        }

        $liqPay = app(LiqPayClient::class);
        if ($leg === 'deferred') {
            $form = $liqPay->checkoutFormData($order, 'deferred');
        } elseif ($leg === 'immediate') {
            $form = $liqPay->checkoutFormData($order, 'immediate');
        } else {
            $form = $liqPay->checkoutFormData($order, 'full');
        }

        return view('checkout.payment-liqpay', [
            'data' => $form['data'],
            'signature' => $form['signature'],
        ]);
    }

    public function success(Request $request, Order $order, ?string $token = null): View|RedirectResponse
    {
        $token = $token !== null && $token !== '' ? $token : (string) $request->query('token', '');
        if (! $order->successTokenMatches($token)) {
            abort(404);
        }

        app(GuestOrderAccess::class)->claim($order);

        $this->applyWayForPayReturnIfApproved($request, $order);
        $order->refresh();
        $this->rememberWayForPayReferenceFromReturnUrl($request, $order);
        $order->refresh();
        $this->syncWayForPayStatusFromGateway($order);
        $order->refresh();

        return view('checkout.success', [
            'order' => $order,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $item
     */
    private function lineDefersOnlinePayment(array $item): bool
    {
        $kind = (string) ($item['line_kind'] ?? 'product');
        if ($kind === 'bundle') {
            foreach ($item['bundle_items'] ?? [] as $bi) {
                $pid = (int) ($bi['product_id'] ?? 0);
                if ($pid > 0 && $this->categoryCheckoutRules->productIdDefersOnlinePayment($pid)) {
                    return true;
                }
            }

            return false;
        }

        $product = $item['product'] ?? null;
        if ($product instanceof Product) {
            return $this->categoryCheckoutRules->lineDefersOnlinePayment($item);
        }

        return false;
    }

    private function resolveCheckoutPaymentLeg(Order $order, Request $request): string
    {
        $q = $request->query('leg');
        if (in_array($q, ['immediate', 'deferred', 'full'], true)) {
            return $q;
        }

        if ($order->mixed_payment_plan && $order->checkout_payment_method === 'online') {
            if ($order->immediate_portion_paid_at === null && $order->effectiveImmediateSubtotal() > 0.00001) {
                return 'immediate';
            }

            return 'deferred';
        }

        if ($order->deferred_online_payment && ! $order->mixed_payment_plan && $order->effectiveDeferredSubtotal() > 0.00001) {
            return 'deferred';
        }

        return 'full';
    }

    private function applyWayForPayReturnIfApproved(Request $request, Order $order): void
    {
        $data = WayForPayClient::parseCallbackPayload($request);
        if ($data === null || ! array_key_exists('orderReference', $data)) {
            return;
        }

        $wfp = app(WayForPayClient::class);
        if (! $wfp->isConfigured()) {
            return;
        }

        $orderReference = (string) ($data['orderReference'] ?? '');
        if (! $wfp->verifyServiceCallbackSignature($data)) {
            Log::warning('WayForPay return: bad signature', [
                'orderReference' => $orderReference,
                'merchantAccount' => $data['merchantAccount'] ?? null,
                'configuredMerchantAccount' => $wfp->merchantAccount(),
                'transactionStatus' => $data['transactionStatus'] ?? null,
                'reasonCode' => $data['reasonCode'] ?? null,
                'amount' => $data['amount'] ?? null,
            ]);

            return;
        }

        $parsed = WayForPayClient::parseOrderReference($orderReference);
        if ((int) $parsed['orderId'] !== (int) $order->id) {
            Log::warning('WayForPay return: order mismatch', [
                'route_order_id' => $order->id,
                'reference_order_id' => $parsed['orderId'],
                'orderReference' => $orderReference,
            ]);

            return;
        }

        $txStatus = trim((string) ($data['transactionStatus'] ?? ''));
        if (strcasecmp($txStatus, 'Approved') !== 0) {
            app(ApplyUnsuccessfulOrderPayment::class)->apply($order, $txStatus);

            return;
        }

        $amount = isset($data['amount']) ? (float) str_replace(',', '.', (string) $data['amount']) : null;

        app(ApplyApprovedOrderPayment::class)->apply($order, $parsed['leg'], $amount, (string) ($data['currency'] ?? ''));
    }

    private function rememberWayForPayReferenceFromReturnUrl(Request $request, Order $order): void
    {
        $orderReference = trim((string) $request->query('wfp_ref', ''));
        if ($orderReference === '') {
            return;
        }

        $parsed = WayForPayClient::parseOrderReference($orderReference);
        if ((int) $parsed['orderId'] !== (int) $order->id) {
            return;
        }

        $order->forceFill([
            'payment_provider' => 'wayforpay',
            'payment_external_id' => $orderReference,
        ])->save();
    }

    private function syncWayForPayStatusFromGateway(Order $order): void
    {
        if ((string) $order->payment_provider !== 'wayforpay') {
            return;
        }

        if ((string) $order->payment_status === 'paid') {
            return;
        }

        $orderReference = trim((string) $order->payment_external_id);
        if ($orderReference === '') {
            return;
        }

        $wfp = app(WayForPayClient::class);
        if (! $wfp->isConfigured()) {
            return;
        }

        try {
            $data = $wfp->checkStatus($orderReference);
        } catch (\Throwable $exception) {
            Log::warning('WayForPay check status failed', [
                'order_id' => $order->id,
                'orderReference' => $orderReference,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if ($data === null) {
            return;
        }

        if (! $wfp->verifyServiceCallbackSignature($data)) {
            Log::warning('WayForPay check status: bad signature', [
                'order_id' => $order->id,
                'orderReference' => $orderReference,
                'merchantAccount' => $data['merchantAccount'] ?? null,
                'configuredMerchantAccount' => $wfp->merchantAccount(),
                'transactionStatus' => $data['transactionStatus'] ?? null,
                'reasonCode' => $data['reasonCode'] ?? null,
                'amount' => $data['amount'] ?? null,
            ]);

            return;
        }

        $parsed = WayForPayClient::parseOrderReference((string) ($data['orderReference'] ?? ''));
        if ((int) $parsed['orderId'] !== (int) $order->id) {
            return;
        }

        $txStatus = trim((string) ($data['transactionStatus'] ?? ''));
        $amount = isset($data['amount']) ? (float) str_replace(',', '.', (string) $data['amount']) : null;

        if (strcasecmp($txStatus, 'Approved') === 0) {
            app(ApplyApprovedOrderPayment::class)->apply($order, $parsed['leg'], $amount, (string) ($data['currency'] ?? ''));

            return;
        }

        app(ApplyUnsuccessfulOrderPayment::class)->apply($order, $txStatus);
    }

    private function normalizeDeliveryPayload(array $validated): array
    {
        $type = (string) ($validated['delivery_type'] ?? '');

        if ($type === Order::DELIVERY_PICKUP) {
            $validated['delivery_city'] = null;
            $validated['delivery_city_ref'] = null;
            $validated['delivery_branch'] = null;
            $validated['delivery_warehouse_ref'] = null;
            $validated['delivery_street'] = null;
            $validated['delivery_street_ref'] = null;
            $validated['delivery_building'] = null;
            $validated['delivery_flat'] = null;
            $validated['delivery_address'] = null;
            $validated['delivery_lat'] = null;
            $validated['delivery_lng'] = null;

            return $validated;
        }

        if ($type === Order::DELIVERY_NOVA_POSHTA_WAREHOUSE) {
            $validated['delivery_street'] = null;
            $validated['delivery_street_ref'] = null;
            $validated['delivery_building'] = null;
            $validated['delivery_flat'] = null;
            $validated['delivery_address'] = null;
            $validated['delivery_lat'] = null;
            $validated['delivery_lng'] = null;

            return $validated;
        }

        if ($type === Order::DELIVERY_NOVA_POSHTA_COURIER) {
            $validated['delivery_branch'] = null;
            $validated['delivery_warehouse_ref'] = null;
            $validated['delivery_street'] = null;
            $validated['delivery_street_ref'] = null;
            $validated['delivery_building'] = null;
            $validated['delivery_flat'] = null;

            return $validated;
        }

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function allowedDeliveryTypesForCheckout(bool $requiresPickupOnly, bool $pickupEnabled): array
    {
        if ($requiresPickupOnly) {
            return $pickupEnabled ? [Order::DELIVERY_PICKUP] : [];
        }

        $types = [
            Order::DELIVERY_NOVA_POSHTA_WAREHOUSE,
            Order::DELIVERY_NOVA_POSHTA_COURIER,
        ];

        if ($pickupEnabled) {
            array_unshift($types, Order::DELIVERY_PICKUP);
        }

        return $types;
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
    private function resolveCartItems(array $cart, bool $forCheckout = false): Collection
    {
        return $this->cartDrawer->fromCart($cart, $forCheckout)['items'];
    }

    /**
     * @return array<string, array{
     *     line_kind: 'product'|'bundle',
     *     product_id?: int,
     *     bundle_id?: int,
     *     qty: int,
     *     option_value_ids: list<int>
     * }>
     */
    private function getCart(Request $request): array
    {
        $cart = $request->session()->get('cart', []);

        return ShopCart::normalize(is_array($cart) ? $cart : []);
    }
}
