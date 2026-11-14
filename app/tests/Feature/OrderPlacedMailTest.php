<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Support\ShopMailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderPlacedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_placed_html_includes_branding_and_totals(): void
    {
        $order = Order::query()->create([
            'customer_name' => 'Олена',
            'customer_phone' => '380000000000',
            'customer_email' => 'buyer@example.com',
            'total' => 573,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);
        $order->items()->create([
            'title_snapshot' => 'Гамак для гризунів',
            'price' => 573,
            'qty' => 1,
            'line_total' => 573,
            'option_value_ids' => [],
        ]);

        $html = (new OrderPlaced($order->fresh(['items'])))->render();

        $this->assertStringContainsString('ZOOGLE', $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('Гамак для гризунів', $html);
        $this->assertStringNotContainsString('mix-blend-mode', $html);
        $this->assertStringContainsString('Дякуємо за замовлення', $html);
        $this->assertStringContainsString('Олена, починаємо готувати', $html);
        $this->assertStringContainsString('ваше замовлення.', $html);
        $this->assertStringNotContainsString('вже його бачимо', $html);
        $this->assertTrue(
            str_contains($html, 'cid:') || str_contains($html, 'data:image/'),
            'Logo must be embedded in the message'
        );
        $this->assertStringContainsString(route('account.orders.show', ['order' => $order, 'token' => $order->success_token]), $html);
        $this->assertStringContainsString('Переглянути замовлення', $html);
        $this->assertStringContainsString('Олена', $html);
        $this->assertStringContainsString('Адреса', $html);
    }

    public function test_order_placed_html_embeds_product_photo(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('products/mail/hammock.png', $png);

        $product = Product::query()->create([
            'title' => 'Гамак',
            'slug' => 'mail-hammock',
            'price' => 100,
            'photos' => ['products/mail/hammock.png'],
            'is_available' => true,
        ]);

        $order = Order::query()->create([
            'customer_name' => 'Олена',
            'customer_phone' => '380000000000',
            'customer_email' => 'buyer@example.com',
            'total' => 100,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'title_snapshot' => 'Гамак',
            'price' => 100,
            'qty' => 1,
            'line_total' => 100,
            'option_value_ids' => [],
        ]);

        $html = (new OrderPlaced($order->fresh(['items.product'])))->render();
        $embeddedImages = substr_count($html, 'cid:') + substr_count($html, 'data:image/');

        $this->assertGreaterThanOrEqual(2, $embeddedImages, 'Logo and product photo must both be embedded');
        $this->assertSame(
            'products/mail/hammock.png',
            $order->items()->first()->mailPhotoStoragePath()
        );
        $this->assertNotNull(ShopMailBranding::publicImageSrc(null, 'products/mail/hammock.png'));
    }
}
