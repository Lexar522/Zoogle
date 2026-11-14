<?php

namespace App\Services;

use App\Models\User;
use App\Support\ShopCart;
use Illuminate\Http\Request;

class CartPersistence
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function sessionCart(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        return ShopCart::normalize(is_array($request->session()->get('cart')) ? $request->session()->get('cart') : []);
    }

    public function persist(Request $request, ?User $user): void
    {
        if ($user === null) {
            return;
        }

        $user->forceFill([
            'saved_cart' => $this->sessionCart($request),
        ])->save();
    }

    public function mergeSavedIntoSession(Request $request, User $user): void
    {
        $session = $this->sessionCart($request);
        $saved = ShopCart::normalize(is_array($user->saved_cart) ? $user->saved_cart : []);
        $merged = ShopCart::merge($session, $saved);
        if ($request->hasSession()) {
            $request->session()->put('cart', $merged);
        }
        $user->forceFill(['saved_cart' => $merged])->save();
    }

    public function clear(Request $request, ?User $user): void
    {
        if ($request->hasSession()) {
            $request->session()->forget('cart');
        }
        if ($user !== null) {
            $user->forceFill(['saved_cart' => []])->save();
        }
    }
}
