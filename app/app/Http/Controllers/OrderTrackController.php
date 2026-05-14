<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackController extends Controller
{
    public function __invoke(Request $request, Order $order): View
    {
        $token = (string) $request->query('token', '');
        if ($token === '' || ! hash_equals((string) $order->success_token, $token)) {
            abort(404);
        }

        $order->load([
            'items.product.variants',
            'items.bundle.items.product.variants',
        ]);

        return view('orders.track', [
            'order' => $order,
        ]);
    }
}
