<?php

namespace Tests;

use App\Models\ShopCatalogPaginationSetting;
use App\Support\StorefrontCategoryTreeCache;
use App\Support\StorefrontMemo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        StorefrontCategoryTreeCache::bump();
        // Статичний кеш налаштувань інакше переходить у наступний тест.
        ShopCatalogPaginationSetting::forgetResolvedCache();
        StorefrontMemo::flush();
    }
}
