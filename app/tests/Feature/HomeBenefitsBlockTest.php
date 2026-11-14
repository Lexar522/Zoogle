<?php

namespace Tests\Feature;

use App\Models\ShopHomeBenefit;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeBenefitsBlockTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Schema::hasTable('shop_home_benefits')) {
            ShopHomeBenefit::query()->delete();
        }

        parent::tearDown();
    }

    public function test_home_shows_default_benefits_until_admin_saves(): void
    {
        if (! Schema::hasTable('shop_home_benefits')) {
            $this->markTestSkipped('Таблиця shop_home_benefits ще не мігрована');
        }

        ShopHomeBenefit::query()->delete();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Чому обирають саме нас', false)
            ->assertSee('Швидка доставка', false)
            ->assertSee('--benefit:#367df1;', false);
    }

    public function test_disabled_block_is_hidden_and_custom_cards_keep_their_color(): void
    {
        if (! Schema::hasTable('shop_home_benefits')) {
            $this->markTestSkipped('Таблиця shop_home_benefits ще не мігрована');
        }

        ShopHomeBenefit::query()->create([
            'is_enabled' => false,
            'title' => 'Прихований блок',
            'lead' => 'Цього тексту не має бути',
            'items' => [
                ['title' => 'Секрет', 'text' => 'Не показувати', 'color' => '#112233'],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Прихований блок', false)
            ->assertDontSee('Секрет', false);

        ShopHomeBenefit::query()->delete();
        ShopHomeBenefit::query()->create([
            'is_enabled' => true,
            'title' => 'Наші плюси',
            'lead' => 'Коротко про магазин',
            'items' => [
                ['title' => 'Своя картка', 'text' => 'Текст картки', 'color' => '#112233'],
                ['title' => '', 'text' => '', 'color' => '#ffffff'],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Наші плюси', false)
            ->assertSee('Своя картка', false)
            ->assertSee('Текст картки', false)
            ->assertSee('--benefit:#112233;', false)
            ->assertDontSee('Чому обирають саме нас', false);
    }
}
