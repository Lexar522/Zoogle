<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Гостьовий доступ — лише до замовлень, для яких у цій сесії пред'явлено токен.
 * Email із чекауту не підтверджений, тому доступ за ним відкривав би чужі замовлення.
 */
final class GuestOrderAccess
{
    public const SESSION_KEY = 'guest_order_ids';

    private const MAX_ORDERS = 50;

    /**
     * @return list<int>
     */
    public function orderIds(): array
    {
        $ids = session(self::SESSION_KEY, []);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function hasOrders(): bool
    {
        return $this->orderIds() !== [];
    }

    public function claim(Order $order): void
    {
        $ids = array_values(array_unique([...$this->orderIds(), (int) $order->id]));

        session([self::SESSION_KEY => array_slice($ids, -self::MAX_ORDERS)]);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function canView(Order $order): bool
    {
        return in_array((int) $order->id, $this->orderIds(), true);
    }

    public function tokenMatches(Request $request, Order $order): bool
    {
        return $order->successTokenMatches((string) $request->query('token', ''));
    }

    public function claimFromValidToken(Request $request, Order $order): bool
    {
        if (! $this->tokenMatches($request, $order)) {
            return false;
        }

        $this->claim($order);

        return true;
    }
}
