<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageCharity;
use App\Models\Order;
use App\Models\ShopCharitySetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CharityPageTest extends TestCase
{
    use RefreshDatabase;

    private const JAR_URL = 'https://send.monobank.ua/jar/ZooGleTest1';

    public function test_charity_is_hidden_until_enabled(): void
    {
        $this->get(route('charity'))->assertNotFound();
        $this->get(route('home'))->assertDontSee(route('charity'), false);
    }

    public function test_enabled_charity_page_shows_text_and_jar_button(): void
    {
        $this->enableCharity(['body' => '<p>Збираємо на <strong>корм</strong></p><script>alert(1)</script>']);

        $this->get(route('charity'))
            ->assertOk()
            ->assertSee('Допоможіть тваринам')
            ->assertSee('<strong>корм</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('href="'.self::JAR_URL.'"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Задонатити');
    }

    public function test_header_links_to_charity_page_when_enabled(): void
    {
        $this->enableCharity();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('charity').'"', false)
            ->assertSee('Благодійність');
    }

    public function test_success_and_order_pages_invite_to_donate(): void
    {
        $this->enableCharity();
        $order = $this->order();

        $this->get(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]))
            ->assertOk()
            ->assertSee('Бажаєте допомогти тваринам')
            ->assertSee('href="'.route('charity').'"', false);

        $this->get(route('orders.track', ['order' => $order, 'token' => $order->success_token]));
        $this->get(route('orders.track', $order))
            ->assertOk()
            ->assertSee('href="'.route('charity').'"', false);
    }

    public function test_success_page_has_no_invite_when_charity_disabled(): void
    {
        $order = $this->order();

        $this->get(route('checkout.success', ['order' => $order->id, 'token' => $order->success_token]))
            ->assertOk()
            ->assertDontSee(route('charity'), false);
    }

    public function test_only_https_monobank_links_are_used_as_jar(): void
    {
        $this->assertTrue(ShopCharitySetting::isAllowedJarUrl(self::JAR_URL));
        $this->assertFalse(ShopCharitySetting::isAllowedJarUrl('javascript:alert(1)'));
        $this->assertFalse(ShopCharitySetting::isAllowedJarUrl('http://send.monobank.ua/jar/x'));
        $this->assertFalse(ShopCharitySetting::isAllowedJarUrl('https://evil.example/jar/x'));

        $this->enableCharity(['jar_url' => 'javascript:alert(1)']);

        $this->get(route('charity'))
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('Задонатити');
    }

    public function test_admin_saves_charity_settings_and_rejects_foreign_jar_links(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get('/admin/charity')->assertOk();

        Livewire::test(ManageCharity::class)
            ->set('data.is_enabled', true)
            ->set('data.jar_url', 'https://evil.example/jar/x')
            ->call('save')
            ->assertHasErrors(['data.jar_url']);
        $this->assertFalse(ShopCharitySetting::isEnabled());

        Livewire::test(ManageCharity::class)
            ->set('data.is_enabled', true)
            ->set('data.jar_url', self::JAR_URL)
            ->set('data.title', 'Збір на корм')
            ->call('save')
            ->assertHasNoErrors();

        ShopCharitySetting::forgetResolvedCache();
        $this->assertTrue(ShopCharitySetting::isEnabled());
        $this->get(route('charity'))->assertOk()->assertSee('Збір на корм');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function enableCharity(array $overrides = []): void
    {
        ShopCharitySetting::query()->create([
            'is_enabled' => true,
            'jar_url' => self::JAR_URL,
            ...$overrides,
        ]);
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
