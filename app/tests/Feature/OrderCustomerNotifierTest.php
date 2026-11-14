<?php

namespace Tests\Feature;

use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderUpdate;
use App\Services\OrderCustomerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderCustomerNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_sends_email_and_stores_history(): void
    {
        Mail::fake();

        $order = $this->makeOrder();

        $update = app(OrderCustomerNotifier::class)->apply(
            $order,
            Order::STATUS_PROCESSING,
            'Зібрали замовлення, готуємо до відправки.',
            true,
        );

        $this->assertInstanceOf(OrderUpdate::class, $update);
        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        $this->assertSame(Order::STATUS_NEW, $update->previous_status);
        $this->assertSame('Зібрали замовлення, готуємо до відправки.', $update->message);
        $this->assertNotNull($update->email_sent_at);

        Mail::assertSent(OrderStatusUpdated::class, function (OrderStatusUpdated $mail) use ($order): bool {
            return $mail->order->is($order)
                && $mail->hasTo('buyer@example.com');
        });
    }

    public function test_status_change_without_email_still_visible_on_track_page(): void
    {
        Mail::fake();

        $order = $this->makeOrder();

        app(OrderCustomerNotifier::class)->apply(
            $order,
            Order::STATUS_SHIPPED,
            'Відправили Новою Поштою.',
            false,
        );

        Mail::assertNothingSent();
        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);

        $this->followingRedirects()
            ->get(route('orders.track', ['order' => $order, 'token' => $order->success_token]))
            ->assertOk()
            ->assertSee('Відправили Новою Поштою.', false)
            ->assertSee('Оновлення від магазину', false);
    }

    public function test_missing_email_skips_mail_but_keeps_update(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['customer_email' => null]);

        $update = app(OrderCustomerNotifier::class)->apply(
            $order,
            Order::STATUS_PAID,
            null,
            true,
        );

        $this->assertNotNull($update);
        $this->assertNull($update->email_sent_at);
        Mail::assertNothingSent();
    }

    public function test_internal_comment_is_not_emailed(): void
    {
        Mail::fake();

        $order = $this->makeOrder(['comment' => 'Внутрішнє: передзвонити']);

        app(OrderCustomerNotifier::class)->notifyAfterSave(
            $order,
            Order::STATUS_NEW,
            '',
            true,
        );

        Mail::assertNothingSent();
        $this->assertSame(0, $order->updates()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'customer_name' => 'Олена',
            'customer_phone' => '380000000000',
            'customer_email' => 'buyer@example.com',
            'total' => 250,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ], $overrides));
    }
}
