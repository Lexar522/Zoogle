<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_lists_and_searches_published_articles(): void
    {
        $rabbit = $this->makeRabbitCategory();

        ProductCareArticle::query()->create([
            'category_value_id' => $rabbit->id,
            'title' => 'Догляд за кроликом',
            'slug' => 'krolik-care',
            'excerpt' => 'Сіно і вода',
            'body' => '<p>Кролик пʼє багато води</p>',
            'is_published' => true,
        ]);
        ProductCareArticle::query()->create([
            'category_value_id' => $rabbit->id,
            'title' => 'Хомʼяк у клітці',
            'slug' => 'hamster-care',
            'body' => '<p>Інша стаття</p>',
            'is_published' => true,
        ]);
        ProductCareArticle::query()->create([
            'category_value_id' => $rabbit->id,
            'title' => 'Чернетка',
            'slug' => 'draft-care',
            'body' => '<p>Не видно</p>',
            'is_published' => false,
        ]);

        $this->get(route('care.index'))
            ->assertOk()
            ->assertSee('Гризуни')
            ->assertSee('Кролик')
            ->assertSee(__('shop.care_library_search_submit'))
            ->assertDontSee('Догляд за кроликом')
            ->assertDontSee('Хомʼяк у клітці')
            ->assertDontSee('Чернетка');

        $this->get(route('care.index', ['q' => 'кролик']))
            ->assertOk()
            ->assertSee('Догляд за кроликом')
            ->assertDontSee('Хомʼяк у клітці');

        $this->get(route('care.index', ['category' => $rabbit->id]))
            ->assertOk()
            ->assertSee('Догляд за кроликом')
            ->assertSee('Хомʼяк у клітці');

        $this->get(route('care.show', 'krolik-care'))
            ->assertOk()
            ->assertSee('Кролик пʼє багато води')
            ->assertSee('Тварини → Гризуни → Кролик');
    }

    public function test_product_inherits_articles_from_category_ancestors(): void
    {
        $rabbit = $this->makeRabbitCategory();
        $rodents = OptionValue::query()->where('slug', 'gryzuny-care-lib')->firstOrFail();
        $hamster = OptionValue::query()->create([
            'option_group_id' => $rodents->option_group_id,
            'parent_id' => $rodents->id,
            'name' => 'Хомʼяк',
            'slug' => 'homyak-care-lib',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        ProductCareArticle::query()->create([
            'category_value_id' => $rabbit->id,
            'title' => 'Лише кролики',
            'slug' => 'only-rabbits',
            'body' => '<p>Кролик</p>',
            'is_published' => true,
        ]);
        ProductCareArticle::query()->create([
            'category_value_id' => $rodents->id,
            'title' => 'Усі гризуни',
            'slug' => 'all-rodents',
            'body' => '<p>Гризун</p>',
            'is_published' => true,
        ]);

        $rabbitProduct = $this->makeProduct('Сіно для кролика', 'hay-rabbit', $rabbit);
        $hamsterProduct = $this->makeProduct('Корм хомʼяка', 'hamster-food', $hamster);

        $this->get(route('catalog.show', $rabbitProduct->slug))
            ->assertOk()
            ->assertSee('Лише кролики')
            ->assertSee('Усі гризуни');

        $this->get(route('catalog.show', $hamsterProduct->slug))
            ->assertOk()
            ->assertSee('Усі гризуни')
            ->assertDontSee('Лише кролики');
    }

    public function test_home_shows_care_tips_button_without_article_cards(): void
    {
        $rabbit = $this->makeRabbitCategory();
        ProductCareArticle::query()->create([
            'category_value_id' => $rabbit->id,
            'title' => 'Випадкова порада',
            'slug' => 'random-care',
            'excerpt' => 'Коротко',
            'body' => '<p>Текст</p>',
            'is_published' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('shop.home_care_title'))
            ->assertSee(route('care.index'), false)
            ->assertDontSee('Випадкова порада');
    }

    public function test_library_hub_lists_only_animal_categories(): void
    {
        $this->makeRabbitCategory();
        $group = OptionGroup::query()->where('slug', 'category')->firstOrFail();
        OptionValue::query()->create([
            'option_group_id' => $group->id,
            'parent_id' => null,
            'name' => 'Корми',
            'slug' => 'kormy-care-lib',
            'sort_order' => 9,
            'is_active' => true,
        ]);

        $this->get(route('care.index'))
            ->assertOk()
            ->assertSee('Гризуни')
            ->assertSee('Кролик')
            ->assertDontSee('Корми');
    }

    public function test_unpublished_library_article_is_not_public(): void
    {
        $rabbit = $this->makeRabbitCategory();
        ProductCareArticle::query()->create([
            'category_value_id' => $rabbit->id,
            'title' => 'Сховано',
            'slug' => 'hidden-care',
            'body' => '<p>Ні</p>',
            'is_published' => false,
        ]);

        $this->get(route('care.show', 'hidden-care'))->assertNotFound();
    }

    private function makeRabbitCategory(): OptionValue
    {
        $group = OptionGroup::query()->firstOrCreate([
            'slug' => 'category',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
        ], [
            'name' => 'Категорія',
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);

        $animals = OptionValue::query()->firstOrCreate([
            'option_group_id' => $group->id,
            'slug' => 'tvaryny-care-lib',
        ], [
            'name' => 'Тварини',
            'parent_id' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $rodents = OptionValue::query()->firstOrCreate([
            'option_group_id' => $group->id,
            'slug' => 'gryzuny-care-lib',
        ], [
            'name' => 'Гризуни',
            'parent_id' => $animals->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return OptionValue::query()->firstOrCreate([
            'option_group_id' => $group->id,
            'slug' => 'krolik-care-lib',
        ], [
            'name' => 'Кролик',
            'parent_id' => $rodents->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeProduct(string $title, string $slug, OptionValue $leaf): Product
    {
        $root = $leaf;
        while ($root->parent_id) {
            $root = OptionValue::query()->findOrFail($root->parent_id);
        }

        return Product::query()->create([
            'title' => $title,
            'slug' => $slug,
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => $leaf->id === $root->id ? null : $leaf->id,
            'price' => 100,
            'is_available' => true,
            'published_at' => now(),
        ]);
    }
}
