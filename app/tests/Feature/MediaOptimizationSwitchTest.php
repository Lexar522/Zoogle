<?php

namespace Tests\Feature;

use App\Models\ShopMediaSetting;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MediaOptimizationSwitchTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Schema::hasTable('shop_media_settings')) {
            ShopMediaSetting::query()->delete();
        }
        ShopMediaSetting::forgetOptimizeImagesCache();

        parent::tearDown();
    }

    public function test_catalog_card_defers_webp_when_optimization_is_on(): void
    {
        if (! Schema::hasTable('shop_media_settings')) {
            $this->markTestSkipped('Таблиця shop_media_settings ще не мігрована');
        }

        ShopMediaSetting::forgetOptimizeImagesCache();

        $html = view('catalog.partials.product-gallery-media', [
            'item' => [
                'path' => 'products/1/photos/a.jpg',
                'url' => 'http://example.test/storage/products/1/photos/a.jpg',
                'card_url' => 'http://example.test/storage/products/1/photos/a.card.webp',
                'type' => 'image',
            ],
            'catalogCard' => true,
            'deferSrc' => true,
            'fetchpriority' => 'high',
        ])->render();

        $this->assertStringContainsString('data-src="http://example.test/storage/products/1/photos/a.card.webp"', $html);
        $this->assertStringContainsString('product-card__photo--pending', $html);
        $this->assertStringNotContainsString('src="http://example.test/storage/products/1/photos/a.jpg"', $html);
    }

    public function test_catalog_card_offers_small_copy_through_srcset(): void
    {
        if (! Schema::hasTable('shop_media_settings')) {
            $this->markTestSkipped('Таблиця shop_media_settings ще не мігрована');
        }

        ShopMediaSetting::forgetOptimizeImagesCache();
        $item = [
            'path' => 'products/1/photos/a.jpg',
            'url' => 'http://example.test/storage/products/1/photos/a.jpg',
            'card_url' => 'http://example.test/storage/products/1/photos/a.card.webp',
            'card_small_url' => 'http://example.test/storage/products/1/photos/a.thumb.webp',
            'type' => 'image',
        ];
        $srcset = 'http://example.test/storage/products/1/photos/a.thumb.webp 320w, http://example.test/storage/products/1/photos/a.card.webp 640w';

        $eager = view('catalog.partials.product-gallery-media', ['item' => $item, 'catalogCard' => true])->render();
        $deferred = view('catalog.partials.product-gallery-media', ['item' => $item, 'catalogCard' => true, 'deferSrc' => true])->render();

        $this->assertStringContainsString('srcset="'.$srcset.'"', $eager);
        $this->assertStringContainsString('sizes="', $eager);
        $this->assertStringContainsString('data-srcset="'.$srcset.'"', $deferred);
        $this->assertStringNotContainsString(' srcset="', $deferred);
    }

    public function test_catalog_card_uses_original_immediately_when_optimization_is_off(): void
    {
        if (! Schema::hasTable('shop_media_settings')) {
            $this->markTestSkipped('Таблиця shop_media_settings ще не мігрована');
        }

        ShopMediaSetting::query()->create(['optimize_images' => false]);
        ShopMediaSetting::forgetOptimizeImagesCache();

        $html = view('catalog.partials.product-gallery-media', [
            'item' => [
                'path' => 'products/1/photos/anim.gif',
                'url' => 'http://example.test/storage/products/1/photos/anim.gif',
                'card_url' => 'http://example.test/storage/products/1/photos/anim.poster.webp',
                'type' => 'gif',
            ],
            'catalogCard' => true,
            'deferSrc' => true,
        ])->render();

        $this->assertStringContainsString('src="http://example.test/storage/products/1/photos/anim.gif"', $html);
        $this->assertStringContainsString('data-zoogle-animated-gif="1"', $html);
        $this->assertStringNotContainsString('anim.poster.webp', $html);
        $this->assertStringNotContainsString('data-src=', $html);
    }
}
