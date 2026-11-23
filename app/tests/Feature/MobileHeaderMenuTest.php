<?php

namespace Tests\Feature;

use App\Models\ShopCharitySetting;
use App\Models\ShopIntegrationSetting;
use App\Models\ShopSponsorshipSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileHeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_burger_menu_login_icon_and_cart(): void
    {
        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $mobileBar = $this->between($html, 'data-mobile-actions', '</div>');
        $this->assertStringContainsString('data-mobile-menu-open', $mobileBar);
        $this->assertStringContainsString('data-cart-open', $mobileBar);
        $this->assertStringContainsString('href="'.route('login').'"', $mobileBar);

        $menu = $this->between($html, 'id="mobile-menu"', '</nav>');
        $this->assertStringContainsString('href="'.route('catalog.index').'"', $menu);
        $this->assertStringContainsString('href="'.route('care.index').'"', $menu);
        $this->assertStringContainsString('href="'.route('delivery-payment').'"', $menu);
        $this->assertStringNotContainsString(route('sponsorship.index'), $menu);
    }

    public function test_signed_in_user_has_account_in_menu_instead_of_login_icon(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Олена Коваль']));

        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $mobileBar = $this->between($html, 'data-mobile-actions', '</div>');
        $this->assertStringNotContainsString(route('login'), $mobileBar);
        $menu = $this->between($html, 'id="mobile-menu"', '</nav>');
        $this->assertStringContainsString('href="'.route('account.index').'"', $menu);
        $this->assertStringContainsString('Олена Коваль', $menu);
    }

    public function test_menu_lists_sponsorship_and_charity_when_enabled(): void
    {
        ShopCharitySetting::query()->create(['is_enabled' => true, 'jar_url' => 'https://send.monobank.ua/jar/A1']);
        ShopSponsorshipSetting::query()->create(['is_enabled' => true, 'jar_url' => 'https://send.monobank.ua/jar/B2']);

        $menu = $this->between((string) $this->get(route('home'))->getContent(), 'id="mobile-menu"', '</nav>');

        $this->assertStringContainsString('href="'.route('sponsorship.index').'"', $menu);
        $this->assertStringContainsString('href="'.route('charity').'"', $menu);
    }

    public function test_contacts_move_from_scroll_header_to_floating_corner_button(): void
    {
        ShopIntegrationSetting::record()->forceFill(['contact_phone' => '+380991112233'])->save();

        $html = (string) $this->get(route('home'))->assertOk()->getContent();

        $compactBar = $this->between($html, 'data-site-header-compact-bar', 'data-mobile-actions');
        $this->assertStringNotContainsString('site-header__contact-card', $compactBar);

        $floating = $this->between($html, 'data-floating-contacts', '</aside>');
        $this->assertStringContainsString('data-floating-contacts-toggle', $floating);
        $this->assertStringContainsString('floating-contacts__icon--phone', $floating);
        $this->assertStringContainsString('tel:+380991112233', $floating);

        $menu = $this->between($html, 'id="mobile-menu"', '</nav>');
        $this->assertStringContainsString('tel:+380991112233', $menu);
        $this->assertStringNotContainsString('href="'.route('contacts').'"', $menu);
    }

    public function test_floating_contacts_are_absent_without_contacts(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-floating-contacts', false);
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, "Не знайдено «{$start}»");
        $to = strpos($html, $end, $from + strlen($start));

        return substr($html, $from, $to === false ? null : $to - $from);
    }
}
