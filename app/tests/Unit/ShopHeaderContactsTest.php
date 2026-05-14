<?php

namespace Tests\Unit;

use App\Models\ShopIntegrationSetting;
use App\Support\ShopHeaderContacts;
use Tests\TestCase;

class ShopHeaderContactsTest extends TestCase
{
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
}
