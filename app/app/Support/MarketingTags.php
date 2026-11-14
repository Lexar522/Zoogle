<?php

namespace App\Support;

use App\Models\Order;

/**
 * Лічильники Google Analytics 4 і Meta Pixel. ID задаються в .env.
 */
final class MarketingTags
{
    private const SESSION_KEY = 'marketing_tracked_orders';

    public static function ga4Id(): ?string
    {
        $id = trim((string) config('shop.marketing.ga4_id'));

        return preg_match('/^G-[A-Z0-9]+$/', $id) === 1 ? $id : null;
    }

    public static function metaPixelId(): ?string
    {
        $id = trim((string) config('shop.marketing.meta_pixel_id'));

        return preg_match('/^\d+$/', $id) === 1 ? $id : null;
    }

    public static function enabled(): bool
    {
        return self::ga4Id() !== null || self::metaPixelId() !== null;
    }

    /**
     * Покупку рахуємо один раз, хоч сторінку успіху можуть оновлювати.
     */
    public static function shouldTrackPurchase(Order $order): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $tracked = session(self::SESSION_KEY, []);
        $tracked = is_array($tracked) ? $tracked : [];
        if (in_array((int) $order->id, $tracked, true)) {
            return false;
        }

        session([self::SESSION_KEY => [...array_slice($tracked, -49), (int) $order->id]]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function purchasePayload(Order $order): array
    {
        return [
            'transaction_id' => (string) $order->number,
            'currency' => 'UAH',
            'value' => (float) $order->total,
            'items' => $order->items->map(fn ($item): array => [
                'id' => (string) ($item->product_id ?? 'bundle-'.$item->bundle_id),
                'name' => (string) $item->title_snapshot,
                'price' => (float) $item->price,
                'quantity' => (int) $item->qty,
            ])->values()->all(),
        ];
    }
}
