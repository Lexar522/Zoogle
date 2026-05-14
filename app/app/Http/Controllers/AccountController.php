<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\LiqPayClient;
use App\Services\Payments\WayForPayClient;
use App\Support\OnlinePaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $recentOrders = Order::query()
            ->forAccountUser($user)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $favoritesCount = $user->favoriteProducts()->count();
        $ordersTotal = Order::query()->forAccountUser($user)->count();

        return view('account.dashboard', [
            'user' => $user,
            'recentOrders' => $recentOrders,
            'favoritesCount' => $favoritesCount,
            'ordersTotal' => $ordersTotal,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->forceFill([
            'first_name' => $validated['first_name'] !== null && $validated['first_name'] !== ''
                ? trim($validated['first_name'])
                : null,
            'last_name' => $validated['last_name'] !== null && $validated['last_name'] !== ''
                ? trim($validated['last_name'])
                : null,
            'phone' => $validated['phone'] !== null && $validated['phone'] !== ''
                ? trim($validated['phone'])
                : null,
        ])->save();

        return redirect()->route('account.index')->with('status', 'profile-saved');
    }

    public function orders(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $filter = (string) $request->query('payment', 'all');
        if (! in_array($filter, ['all', 'deferred_pending', 'deferred_ready'], true)) {
            $filter = 'all';
        }

        $query = Order::query()->forAccountUser($user);

        if ($filter === 'deferred_pending') {
            $query->where('deferred_online_payment', true)
                ->where('payment_status', 'pending')
                ->whereNull('online_payment_unlocked_at');
        } elseif ($filter === 'deferred_ready') {
            $query->where('deferred_online_payment', true)
                ->where('payment_status', 'pending')
                ->whereNotNull('online_payment_unlocked_at');
        }

        $orders = $query
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('account.orders-index', [
            'orders' => $orders,
            'ordersPaymentFilter' => $filter,
            'onlinePaymentConfigured' => app(OnlinePaymentSettings::class)->isConfigured(),
        ]);
    }

    public function orderShow(Request $request, Order $order): View
    {
        $this->authorize('viewAccount', $order);

        $order->load([
            'items.product.variants',
            'items.bundle.items.product.variants',
        ]);

        return view('account.order-show', [
            'order' => $order,
            'liqPayConfigured' => app(OnlinePaymentSettings::class)->isConfigured(),
        ]);
    }

    public function orderPayment(Request $request, Order $order): View|RedirectResponse
    {
        $this->authorize('viewAccount', $order);

        $pay = app(OnlinePaymentSettings::class);
        if (! $order->canPayDeferredLiqPay() || ! $pay->isConfigured()) {
            return redirect()
                ->route('account.orders.show', $order)
                ->with('error', 'Онлайн-оплата зараз недоступна. Якщо чекаєте після підтвердження менеджера — оновіть сторінку пізніше.');
        }

        if ($pay->provider() === OnlinePaymentSettings::PROVIDER_WAYFORPAY) {
            $form = app(WayForPayClient::class)->purchaseFormFields($order, 'deferred');
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

        $form = app(LiqPayClient::class)->checkoutFormData($order, 'deferred');

        return view('checkout.payment-liqpay', [
            'data' => $form['data'],
            'signature' => $form['signature'],
        ]);
    }

    public function favorites(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $products = $user->favoriteProducts()
            ->where('is_available', true)
            ->orderBy('title')
            ->paginate(24);

        return view('account.favorites', [
            'products' => $products,
        ]);
    }
}
