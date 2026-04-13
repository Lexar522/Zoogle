<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function dashboard(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $recentOrders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $favoritesCount = $user->favoriteProducts()->count();
        $ordersTotal = Order::query()->where('user_id', $user->id)->count();

        return view('account.dashboard', [
            'user' => $user,
            'recentOrders' => $recentOrders,
            'favoritesCount' => $favoritesCount,
            'ordersTotal' => $ordersTotal,
        ]);
    }

    public function orders(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('account.orders-index', [
            'orders' => $orders,
        ]);
    }

    public function orderShow(Request $request, Order $order): View
    {
        $this->authorize('viewAccount', $order);

        $order->load('items');

        return view('account.order-show', [
            'order' => $order,
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
