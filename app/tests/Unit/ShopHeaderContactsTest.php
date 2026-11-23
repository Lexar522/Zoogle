<?php

namespace Tests\Unit;

use App\Models\ShopContactItem;
use App\Models\ShopIntegrationSetting;
use App\Support\ShopHeaderContacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopHeaderContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_record_returns_empty_list(): void
    {
        $this->assertSame([], ShopHeaderContacts::itemsFrom(null));
    }

    public function test_phone_ua_leading_zero_normalizes_tel_href(): void
    {
        $s = new ShopIntegrationSetting(['contact_phone' => '+38 099 111 22 33']);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('tel:+380991112233', $items[0]['href']);
        $this->assertSame('phone', $items[0]['kind']);
    }

    public function test_invalid_email_omits_item(): void
    {
        $s = new ShopIntegrationSetting(['contact_email' => 'not-an-email']);
        $this->assertSame([], ShopHeaderContacts::itemsFrom($s));
    }

    public function test_instagram_nick_resolves_href(): void
    {
        $s = new ShopIntegrationSetting(['contact_instagram' => '@zoogle_test']);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('https://www.instagram.com/zoogle_test/', $items[0]['href']);
        $this->assertTrue($items[0]['external']);
    }

    public function test_telegram_nick_resolves_t_me(): void
    {
        $s = new ShopIntegrationSetting(['contact_telegram' => 'zoogle_channel']);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('https://t.me/zoogle_channel', $items[0]['href']);
    }

    public function test_whatsapp_wa_me_url_accepted(): void
    {
        $s = new ShopIntegrationSetting(['contact_whatsapp' => 'https://wa.me/380991112233']);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('whatsapp', $items[0]['kind']);
    }

    public function test_whatsapp_plain_phone_number_accepted(): void
    {
        $s = new ShopIntegrationSetting(['contact_whatsapp' => '+380676546522']);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('https://wa.me/380676546522', $items[0]['href']);
        $this->assertSame('whatsapp', $items[0]['kind']);
    }

    public function test_viber_plain_phone_number_accepted(): void
    {
        $s = new ShopIntegrationSetting(['contact_viber' => '+380676546521']);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('viber://chat?number=380676546521', $items[0]['href']);
        $this->assertSame('viber', $items[0]['kind']);
    }

    public function test_messenger_items_from_includes_only_viber_whatsapp_telegram(): void
    {
        $s = new ShopIntegrationSetting([
            'contact_phone' => '+380991112233',
            'contact_email' => 'a@b.c',
            'contact_telegram' => 'zoogle_channel',
            'contact_whatsapp' => 'https://wa.me/380991112233',
        ]);
        $items = ShopHeaderContacts::messengerItemsFrom($s);
        $kinds = array_map(static fn (array $i): string => $i['kind'], $items);
        $this->assertCount(2, $kinds);
        $this->assertEqualsCanonicalizing(['telegram', 'whatsapp'], $kinds);
    }

    public function test_defer_modal_contact_items_include_phone_and_messengers(): void
    {
        $s = new ShopIntegrationSetting([
            'contact_phone' => '+380991112233',
            'contact_email' => 'only@test.org',
            'contact_telegram' => 'ch_test',
            'contact_viber' => 'https://invite.viber.com/xyz',
        ]);
        $items = ShopHeaderContacts::deferModalContactItemsFrom($s);
        $kinds = array_map(static fn (array $i): string => $i['kind'], $items);
        $this->assertEqualsCanonicalizing(['phone', 'viber', 'telegram'], $kinds);
        $this->assertNotContains('email', $kinds);
    }

    public function test_multiple_phones_from_legacy_columns(): void
    {
        $s = new ShopIntegrationSetting([
            'contact_phone' => '+380991112233',
        ]);
        $items = ShopHeaderContacts::itemsFrom($s);
        $this->assertCount(1, $items);
        $this->assertSame('phone', $items[0]['kind']);
    }

    public function test_tiktok_nick_resolves_href(): void
    {
        $settings = ShopIntegrationSetting::record();
        ShopContactItem::query()->create([
            'sort_order' => 0,
            'kind' => 'tiktok',
            'value' => '@zoogle.forpet',
        ]);

        $items = ShopHeaderContacts::itemsFrom($settings);
        $this->assertCount(1, $items);
        $this->assertSame('tiktok', $items[0]['kind']);
        $this->assertSame('https://www.tiktok.com/@zoogle.forpet', $items[0]['href']);
        $this->assertSame('@zoogle.forpet', $items[0]['label']);
        $this->assertTrue($items[0]['external']);
    }

    public function test_tiktok_url_passthrough_keeps_username_label(): void
    {
        $settings = ShopIntegrationSetting::record();
        ShopContactItem::query()->create([
            'sort_order' => 0,
            'kind' => 'tiktok',
            'value' => 'https://www.tiktok.com/@zoogle.ua',
        ]);

        $items = ShopHeaderContacts::itemsFrom($settings);
        $this->assertCount(1, $items);
        $this->assertSame('https://www.tiktok.com/@zoogle.ua', $items[0]['href']);
        $this->assertSame('@zoogle.ua', $items[0]['label']);
    }

    public function test_contact_items_keep_sort_order(): void
    {
        $settings = ShopIntegrationSetting::record();
        ShopContactItem::query()->create([
            'sort_order' => 2,
            'kind' => 'telegram',
            'value' => 'zoogleuaa',
        ]);
        ShopContactItem::query()->create([
            'sort_order' => 0,
            'kind' => 'phone',
            'value' => '+38093359868188',
        ]);
        ShopContactItem::query()->create([
            'sort_order' => 1,
            'kind' => 'tiktok',
            'value' => '@zoogle',
        ]);

        $items = ShopHeaderContacts::itemsFrom($settings);
        $this->assertSame(['phone', 'tiktok', 'telegram'], array_column($items, 'kind'));
    }

    public function test_footer_groups_sort_direct_then_messengers_then_social(): void
    {
        $items = [
            ['key' => 'ig', 'kind' => 'instagram', 'href' => '#', 'label' => '@a', 'external' => true],
            ['key' => 'wa', 'kind' => 'whatsapp', 'href' => '#', 'label' => '1', 'external' => true],
            ['key' => 'ph', 'kind' => 'phone', 'href' => '#', 'label' => '2', 'external' => false],
            ['key' => 'vb', 'kind' => 'viber', 'href' => '#', 'label' => '3', 'external' => true],
            ['key' => 'em', 'kind' => 'email', 'href' => '#', 'label' => 'e', 'external' => false],
            ['key' => 'tt', 'kind' => 'tiktok', 'href' => '#', 'label' => '@t', 'external' => true],
        ];

        $groups = ShopHeaderContacts::groupsForFooter($items);

        $this->assertSame(['direct', 'online'], array_keys($groups));
        $this->assertSame(['phone', 'email'], array_column($groups['direct'], 'kind'));
        $this->assertSame(['viber', 'whatsapp', 'instagram', 'tiktok'], array_column($groups['online'], 'kind'));
    }

    public function test_colored_icon_points_to_the_messenger_logo(): void
    {
        $this->assertStringContainsString('viber.svg', ShopHeaderContacts::coloredIconUrl('viber'));
        $this->assertStringContainsString('t_logo.svg', ShopHeaderContacts::coloredIconUrl('telegram'));
    }
}
