<?php

namespace Tests\Unit;

use App\Models\ShopMediaSetting;
use App\Support\ListingPhotoDerivative;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingPhotoDerivativeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('shop_media_settings')) {
            ShopMediaSetting::query()->delete();
        }
        ShopMediaSetting::forgetOptimizeImagesCache();
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('shop_media_settings')) {
            ShopMediaSetting::query()->delete();
        }
        ShopMediaSetting::forgetOptimizeImagesCache();

        parent::tearDown();
    }

    public function test_jpeg_card_webp_keeps_original_and_limits_edge(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('GD webp/jpeg недоступний');
        }

        $image = imagecreatetruecolor(1200, 800);
        $tmp = tempnam(sys_get_temp_dir(), 'zoogle-jpg');
        imagejpeg($image, $tmp, 90);
        imagedestroy($image);
        Storage::disk('public')->put('products/1/photos/shot.jpg', (string) file_get_contents($tmp));
        @unlink($tmp);

        $relative = ListingPhotoDerivative::ensureForStoredPath('products/1/photos/shot.jpg');

        $this->assertSame('products/1/photos/shot.card.webp', $relative);
        $this->assertTrue(Storage::disk('public')->exists('products/1/photos/shot.jpg'));
        $this->assertTrue(ListingPhotoDerivative::isDerivativePath((string) $relative));

        $info = getimagesize(Storage::disk('public')->path((string) $relative));
        $this->assertIsArray($info);
        $this->assertLessThanOrEqual(640, max($info[0], $info[1]));
        $this->assertStringContainsString('shot.card.webp', ListingPhotoDerivative::cardUrlForPath('products/1/photos/shot.jpg'));
    }

    /** Телефон бере копію 320px замість 640px: картка там лише ~170px завширшки. */
    public function test_card_copy_comes_with_small_copy_for_phones(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('GD webp/jpeg недоступний');
        }

        $image = imagecreatetruecolor(1200, 800);
        $tmp = tempnam(sys_get_temp_dir(), 'zoogle-jpg');
        imagejpeg($image, $tmp, 90);
        imagedestroy($image);
        Storage::disk('public')->put('products/1/photos/small.jpg', (string) file_get_contents($tmp));
        @unlink($tmp);

        $this->assertSame('', ListingPhotoDerivative::thumbUrlForPath('products/1/photos/small.jpg'), 'Без копії srcset не потрібен');

        ListingPhotoDerivative::ensureForStoredPath('products/1/photos/small.jpg');

        $thumb = 'products/1/photos/small.thumb.webp';
        $this->assertTrue(Storage::disk('public')->exists($thumb));
        $this->assertTrue(ListingPhotoDerivative::isDerivativePath($thumb));
        $info = getimagesize(Storage::disk('public')->path($thumb));
        $this->assertIsArray($info);
        $this->assertSame(320, max($info[0], $info[1]));
        $this->assertStringContainsString('small.thumb.webp', ListingPhotoDerivative::thumbUrlForPath('products/1/photos/small.jpg'));
    }

    public function test_switch_off_serves_original_instead_of_card_copy(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('GD webp/jpeg недоступний');
        }
        if (! Schema::hasTable('shop_media_settings')) {
            $this->markTestSkipped('Таблиця shop_media_settings ще не мігрована');
        }

        $image = imagecreatetruecolor(80, 40);
        $tmp = tempnam(sys_get_temp_dir(), 'zoogle-switch');
        imagejpeg($image, $tmp, 90);
        imagedestroy($image);
        Storage::disk('public')->put('products/1/photos/switch.jpg', (string) file_get_contents($tmp));
        @unlink($tmp);

        $this->assertNotNull(ListingPhotoDerivative::ensureForStoredPath('products/1/photos/switch.jpg'));
        $this->assertStringContainsString('switch.card.webp', ListingPhotoDerivative::cardUrlForPath('products/1/photos/switch.jpg'));

        ShopMediaSetting::query()->create(['optimize_images' => false]);
        ShopMediaSetting::forgetOptimizeImagesCache();

        $url = ListingPhotoDerivative::cardUrlForPath('products/1/photos/switch.jpg');
        $this->assertStringContainsString('switch.jpg', $url);
        $this->assertStringNotContainsString('.card.webp', $url);
    }

    public function test_gif_without_poster_does_not_use_original_on_card(): void
    {
        Storage::disk('public')->put('products/1/photos/anim.gif', 'gif');

        $this->assertSame('', ListingPhotoDerivative::cardUrlForPath('products/1/photos/anim.gif'));
        $this->assertTrue(ListingPhotoDerivative::isDerivativePath('products/1/photos/anim.poster.webp'));
    }

    public function test_png_bytes_with_gif_extension_still_get_a_poster(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD webp/png недоступний');
        }

        $image = imagecreatetruecolor(80, 40);
        $tmp = tempnam(sys_get_temp_dir(), 'zoogle-png-gif');
        imagepng($image, $tmp);
        imagedestroy($image);
        Storage::disk('public')->put('products/1/photos/mislabeled.gif', (string) file_get_contents($tmp));
        @unlink($tmp);

        $relative = ListingPhotoDerivative::ensureForStoredPath('products/1/photos/mislabeled.gif');

        $this->assertSame('products/1/photos/mislabeled.poster.webp', $relative);
        $this->assertStringContainsString('mislabeled.poster.webp', ListingPhotoDerivative::cardUrlForPath('products/1/photos/mislabeled.gif'));
    }

    public function test_banner_copy_limits_width(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD webp/png недоступний');
        }

        $image = imagecreatetruecolor(2000, 400);
        $tmp = tempnam(sys_get_temp_dir(), 'zoogle-png');
        imagepng($image, $tmp);
        imagedestroy($image);
        Storage::disk('public')->put('header-banners/9/wide.png', (string) file_get_contents($tmp));
        @unlink($tmp);

        $relative = ListingPhotoDerivative::ensureForStoredPath('header-banners/9/wide.png');

        $this->assertSame('header-banners/9/wide.banner.webp', $relative);
        $info = getimagesize(Storage::disk('public')->path((string) $relative));
        $this->assertIsArray($info);
        $this->assertLessThanOrEqual(1600, $info[0]);
        $this->assertStringContainsString('wide.banner.webp', ListingPhotoDerivative::bannerUrlForPath('header-banners/9/wide.png'));
    }
}
