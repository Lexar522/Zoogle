<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Products\Pages\EditProduct;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditProductSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_product_page_saves_price_and_description(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::query()->where('email', 'admin@sitezoo.local')->firstOrFail();
        $product = $this->makeEditableProduct();

        $this->actingAs($admin);

        Livewire::test(EditProduct::class, ['record' => (string) $product->id])
            ->set('data.price', '1500')
            ->set('data.description', [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Новий опис товару',
                            ],
                        ],
                    ],
                ],
            ])
            ->call('save', false, false)
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertSame('1500.00', number_format((float) $product->price, 2, '.', ''));
        $this->assertStringContainsString('Новий опис товару', (string) $product->description);
    }

    private function makeEditableProduct(): Product
    {
        $categoryGroup = OptionGroup::query()->firstOrCreate(
            [
                'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
                'slug' => 'category',
            ],
            [
                'name' => 'Категорія',
                'selection_mode' => 'single',
                'value_type' => 'text',
                'is_active' => true,
            ]
        );

        $root = OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'animal',
            ],
            [
                'name' => 'Товар',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        return Product::query()->create([
            'title' => 'Test product',
            'slug' => 'test-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'description' => '<p>Старий опис</p>',
            'short_description' => '<p>Короткий</p>',
            'price' => 999,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $categoryGroup->id,
                    'option_value_ids' => [$root->id],
                ],
            ],
        ]);
    }
}
