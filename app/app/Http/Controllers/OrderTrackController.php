<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Support\GuestOrderAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackController extends Controller
{
    /**
     * Посилання з токеном відкривають один раз: замовлення запам'ятовується в сесії, а токен
     * прибирається з адресного рядка (не лишається в історії, логах і пересланих скриншотах).
     */
    public function __invoke(Request $request, Order $order, GuestOrderAccess $guestOrders): View|RedirectResponse
    {
        if ($request->query->has('token')) {
            abort_unless($guestOrders->claimFromValidToken($request, $order), 404);

            return redirect()->route('orders.track', $order);
        }

        $user = $request->user();
        $canView = $guestOrders->canView($order)
            || ($user instanceof User && $user->can('viewAccount', $order));
        abort_unless($canView, 404);

        $order->load([
            'items.product.variants',
            'items.bundle.items.product.variants',
            'updates',
        ]);

        return view('orders.track', [
            'order' => $order,
        ]);
    }
}
