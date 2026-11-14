<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Support\GuestOrderAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowAccountOrdersWithoutGoogle
{
    public function __construct(
        private readonly GuestOrderAccess $guestOrders,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        if ($this->guestOrders->hasOrders()) {
            return $next($request);
        }

        $order = $request->route('order');
        if ($order instanceof Order && $this->guestOrders->claimFromValidToken($request, $order)) {
            return $next($request);
        }

        return redirect()->route('login');
    }
}
