<?php

namespace App\Http\Controllers;

use App\Mail\OrderPlaced;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartDrawerService;
use App\Services\Payments\LiqPayClient;
use App\Support\ShopCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartDrawerService $cartDrawer,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->getCart($request);
        if ($cart === []) {
            return redirect()->route('catalog.index')->with('error', 'Кошик порожній.');
        }

        $items = $this->resolveCartItems($cart, forCheckout: true);

        if ($items->isEmpty()) {
            return redirect()->route('catalog.index')->with('error', 'У кошику немає доступних позицій.');
        }

        $user = Auth::user();

        return view('checkout.index', [
            'items' => $items,
            'total' => $items->sum('line_total'),
            'checkoutPrefill' => [
                'customer_name' => $user?->name ?? '',
                'customer_email' => $user?->email ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'delivery_type' => ['required', 'string', Rule::in([Order::DELIVERY_PICKUP, Order::DELIVERY_COURIER, Order::DELIVERY_NOVA_POSHTA])],
            'delivery_city' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn () => $request->string('delivery_type')->toString() === Order::DELIVERY_NOVA_POSHTA),
            ],
            'delivery_branch' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->string('delivery_type')->toString() === Order::DELIVERY_NOVA_POSHTA),
            ],
            'delivery_address' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () => $request->string('delivery_type')->toString() === Order::DELIVERY_COURIER),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $this->getCart($request);
        if ($cart === []) {
            return redirect()->route('catalog.index')->with('error', 'Кошик порожній.');
        }

        $items = $this->resolveCartItems($cart, forCheckout: true);

        if ($items->isEmpty()) {
            return redirect()->route('catalog.index')->with('error', 'У кошику немає позицій для оформлення.');
        }

        $total = $items->sum('line_total');

        $order = DB::transaction(function () use ($items, $total, $validated, $request): Order {
            $orderPayload = [
                ...$validated,
                'total' => $total,
                'status' => 'new',
                'payment_status' => 'pending',
            ];
            if ($request->user() !== null) {
                $orderPayload['user_id'] = $request->user()->id;
            }

            $order = Order::query()->create($orderPayload);

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
                        'option_value_ids' => null,
                        'bundle_snapshot' => $bundleSnapshot,
                        'price' => $item['unit_price'],
                        'qty' => $qty,
                        'line_total' => $item['line_total'],
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
                    'title_snapshot' => $titleSnapshot,
                    'option_value_ids' => $item['option_value_ids'] ?? [],
                    'price' => $item['unit_price'],
                    'qty' => $qty,
                    'line_total' => $item['line_total'],
                ]);
            }

            DB::afterCommit(function () use ($order): void {
                $email = trim((string) $order->customer_email);
                if ($email !== '') {
                    Mail::to($email)->send(new OrderPlaced($order->fresh(['items'])));
                }
            });

            return $order;
        });

        $request->session()->forget('cart');

        if (app(LiqPayClient::class)->isConfigured()) {
            $order->forceFill([
                'payment_provider' => 'liqpay',
            ])->save();

            return redirect()->route('checkout.payment', [
                'order' => $order->id,
                'token' => $order->success_token,
            ]);
        }

        return redirect()->route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]);
    }

    public function payment(Request $request, Order $order, ?string $token = null): View|RedirectResponse
    {
        $token = $token !== null && $token !== '' ? $token : (string) $request->query('token', '');
        if ($token === '' || ! hash_equals((string) $order->success_token, $token)) {
            abort(404);
        }

        $liqPay = app(LiqPayClient::class);
        if (! $liqPay->isConfigured()) {
            return redirect()->route('checkout.success', ['order' => $order->id, 'token' => $token]);
        }

        $form = $liqPay->checkoutFormData($order);

        return view('checkout.payment-liqpay', [
            'data' => $form['data'],
            'signature' => $form['signature'],
        ]);
    }

    public function success(Request $request, Order $order, ?string $token = null): View|RedirectResponse
    {
        $token = $token !== null && $token !== '' ? $token : (string) $request->query('token', '');
        if ($token === '' || ! hash_equals((string) $order->success_token, $token)) {
            abort(404);
        }

        return view('checkout.success', [
            'order' => $order,
        ]);
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
