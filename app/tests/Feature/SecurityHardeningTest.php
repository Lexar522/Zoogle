<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_track_link_is_remembered_in_session_and_token_leaves_the_address_bar(): void
    {
        $order = $this->order();

        $this->get(route('orders.track', ['order' => $order, 'token' => $order->success_token]))
            ->assertRedirect(route('orders.track', $order));

        $this->get(route('orders.track', $order))
            ->assertOk()
            ->assertSee($order->number, false);
    }

    public function test_track_page_without_token_stays_closed_for_strangers(): void
    {
        $order = $this->order();

        $this->get(route('orders.track', $order))->assertNotFound();
        $this->get(route('orders.track', ['order' => $order, 'token' => 'wrong']))->assertNotFound();
    }

    public function test_order_links_expire_after_the_token_lifetime(): void
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update([
            'placed_at' => now()->subDays(Order::SUCCESS_TOKEN_TTL_DAYS + 1),
        ]);
        $order->refresh();

        $this->get(route('orders.track', ['order' => $order, 'token' => $order->success_token]))->assertNotFound();
        $this->get(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]))->assertNotFound();
        // Кабінет без дійсного токена відправляє гостя на вхід — даних замовлення він не бачить.
        $this->get(route('account.orders.show', ['order' => $order, 'token' => $order->success_token]))
            ->assertRedirect(route('login'));
    }

    public function test_google_login_routes_are_rate_limited(): void
    {
        foreach (['login', 'auth.google.redirect', 'auth.google.callback'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertNotEmpty(
                array_filter($middleware, fn (string $m): bool => str_starts_with($m, 'throttle:')),
                "Маршрут {$name} без обмеження частоти"
            );
        }
    }

    public function test_csp_allows_scripts_only_from_own_site_and_known_services(): void
    {
        $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' /", $csp);
        foreach (['https://unpkg.com', 'https://maps.googleapis.com', 'https://www.googletagmanager.com', 'https://connect.facebook.net'] as $host) {
            $this->assertStringContainsString($host, $csp);
        }
        $this->assertDoesNotMatchRegularExpression('/script-src[^;]*(\s\*|https:\s|https:;)/', $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // Фонові скрипти адмінки (обробка фото) створюються з blob: — без цього браузер їх блокує
        $this->assertStringContainsString("worker-src 'self' blob:", $csp);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'customer_name' => 'Гість',
            'customer_phone' => '380000000000',
            'total' => 10,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);
    }
}
