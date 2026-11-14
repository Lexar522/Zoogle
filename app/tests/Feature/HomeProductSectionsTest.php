<?php

namespace Tests\Feature;

use App\Models\ShopHomeProductSection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeProductSectionsTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Schema::hasTable('shop_home_product_sections')) {
            ShopHomeProductSection::query()->delete();
        }

        parent::tearDown();
    }

    public function test_home_shows_default_section_copy_until_admin_saves(): void
    {
        if (! Schema::hasTable('shop_home_product_sections')) {
            $this->markTestSkipped('Таблиця shop_home_product_sections ще не мігрована');
        }

        ShopHomeProductSection::query()->delete();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Хіти продажів', false)
            ->assertSee('Товари, які найчастіше обирають наші покупці.', false)
            ->assertSee('Рекомендовані товари', false)
            ->assertSee('Позиції, які добре доповнюють покупку або підходять більшості улюбленців.', false);
    }

    public function test_admin_copy_replaces_titles_and_hidden_block_disappears(): void
    {
        if (! Schema::hasTable('shop_home_product_sections')) {
            $this->markTestSkipped('Таблиця shop_home_product_sections ще не мігрована');
        }

        ShopHomeProductSection::query()->create([
            'hits_enabled' => false,
            'hits_title' => 'Приховані хіти',
            'hits_lead' => 'Цього опису хітів не має бути',
            'recommended_enabled' => true,
            'recommended_title' => 'Наш вибір',
            'recommended_lead' => 'Короткий опис рекомендацій',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Приховані хіти', false)
            ->assertDontSee('Цього опису хітів не має бути', false)
            ->assertDontSee('Хіти продажів', false)
            ->assertSee('Наш вибір', false)
            ->assertSee('Короткий опис рекомендацій', false)
            ->assertDontSee('Рекомендовані товари', false);
    }
}
