<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeAndCatalogGridTest extends TestCase
{
    public function test_catalog_without_filters_shows_prompt_instead_of_product_grid(): void
    {
        $response = $this->get(route('catalog.index'));

        $response->assertOk();
        $response->assertViewHas('showCatalogGrid', false);
        $response->assertSee('Оберіть категорію в меню вище', false);
    }

    public function test_home_share_preview_uses_short_copy_and_og_image(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="'.e(__('shop.home_og_title')).'">', false);
        $response->assertSee('<meta property="og:description" content="'.e(__('shop.home_og_description')).'">', false);
        $response->assertSee('images/og-share.png', false);
        $response->assertSee('<meta property="og:image:width" content="1200">', false);
        $response->assertSee('<meta property="og:image:height" content="630">', false);
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('SearchAction', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_catalog_share_preview_uses_pet_slogan_and_og_image(): void
    {
        $response = $this->get(route('catalog.index'));

        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="Каталог — ZOOGLE">', false);
        $response->assertSee('<meta property="og:description" content="Те, що шукає твій улюбленець">', false);
        $response->assertSee('images/og-share.png', false);
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_privacy_share_preview_matches_catalog_card(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('<meta property="og:title" content="'.e(__('shop.static_privacy_title')).' — ZOOGLE">', false);
        $response->assertSee('images/og-share.png', false);
    }

    public function test_home_back_navigation_skips_reload_after_bfcache(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('zoogleReapplyShopStyles', false);
        $response->assertSee('zoogleRevealPendingPhotos', false);
        $response->assertSee('build/assets/shop-', false);
        $response->assertSee('build/assets/category-hub-', false);
        // Скрипт каталогу підключається окремим файлом, щоб браузер його кешував.
        $response->assertSee('js/storefront/catalog.js', false);

        $script = (string) file_get_contents(public_path('js/storefront/catalog.js'));
        $this->assertStringContainsString('catalogRestoredFromCache', $script);
        $this->assertStringContainsString('zoogleRestorePageVisuals', $script);
        $this->assertStringContainsString('catalog-results--listing', $script);
        $this->assertStringContainsString('Не reload() на головній після «Назад»', $script);
    }
}
