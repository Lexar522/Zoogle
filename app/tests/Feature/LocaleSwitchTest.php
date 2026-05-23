<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    public function test_locale_switch_persists_in_session_for_next_request(): void
    {
        $this->get(route('locale.switch', ['locale' => 'en']).'?'.http_build_query(['return' => '/']))
            ->assertRedirect('/');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('lang="en"', false);
    }

    public function test_locale_switch_to_russian_updates_interface_strings(): void
    {
        $this->get(route('locale.switch', ['locale' => 'ru']).'?'.http_build_query(['return' => '/catalog']))
            ->assertRedirect('/catalog');

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(__('shop.catalog_prompt', locale: 'ru'), false);
    }
}
