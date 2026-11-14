<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BundleItemPhotoAndCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        foreach (['products/1/leopard.jpg', 'products/1/yellow.jpg', 'bundles/own.jpg'] as $path) {
            Storage::disk('public')->put($path, 'image');
        }
    }

    public function test_gallery_shows_photo_of_the_selected_option(): void
    {
        [$bundle, $yellow] = $this->bundleWithColorOption();

        $response = $this->get(route('bundles.show', $bundle->slug))->assertOk();

        $response->assertSee('storage/products/1/yellow.jpg', false);
        $response->assertDontSee('storage/products/1/leopard.jpg', false);
        $this->assertSame('products/1/yellow.jpg', $bundle->fresh()->firstCatalogPhotoPath());
        $this->assertGreaterThan(0, $yellow->id);
    }

    public function test_own_bundle_photos_stay_first(): void
    {
        [$bundle] = $this->bundleWithColorOption();
        $bundle->update(['photos' => ['bundles/own.jpg']]);

        $this->get(route('bundles.show', $bundle->slug))
            ->assertOk()
            ->assertSee('storage/bundles/own.jpg', false)
            ->assertDontSee('storage/products/1/leopard.jpg', false);
    }

    public function test_item_without_option_photo_falls_back_to_product_photo(): void
    {
        [$bundle] = $this->bundleWithColorOption();
        BundleItem::query()->where('bundle_id', $bundle->id)->update(['option_value_ids' => null]);

        $this->assertSame('products/1/leopard.jpg', $bundle->fresh()->firstCatalogPhotoPath());
    }

    public function test_gallery_includes_every_item_once_when_toggle_is_on(): void
    {
        [$bundle, , $product] = $this->bundleWithColorOption();
        BundleItem::query()->create(['bundle_id' => $bundle->id, 'product_id' => $product->id, 'qty' => 1, 'sort_order' => 2]);
        $bundle->update(['show_item_photos_in_gallery' => true]);

        $this->assertSame(
            ['products/1/yellow.jpg', 'products/1/leopard.jpg'],
            $bundle->fresh()->galleryPhotoPaths(),
        );
        $this->get(route('bundles.show', $bundle->slug))
            ->assertOk()
            ->assertViewHas('galleryPhotos', ['products/1/yellow.jpg', 'products/1/leopard.jpg']);
    }

    public function test_gallery_hides_item_photos_by_default(): void
    {
        [$bundle, , $product] = $this->bundleWithColorOption();
        BundleItem::query()->create(['bundle_id' => $bundle->id, 'product_id' => $product->id, 'qty' => 1, 'sort_order' => 2]);

        $this->assertFalse($bundle->fresh()->show_item_photos_in_gallery);
        $this->assertSame(['products/1/yellow.jpg'], $bundle->fresh()->galleryPhotoPaths());
        $this->get(route('bundles.show', $bundle->slug))
            ->assertOk()
            ->assertViewHas('galleryPhotos', ['products/1/yellow.jpg'])
            ->assertDontSee('bundle-thumbs" aria-label', false);
    }

    public function test_own_photos_win_over_item_photos_even_when_toggle_is_on(): void
    {
        [$bundle] = $this->bundleWithColorOption();
        $bundle->update(['photos' => ['bundles/own.jpg'], 'show_item_photos_in_gallery' => true]);

        $this->get(route('bundles.show', $bundle->slug))
            ->assertOk()
            ->assertViewHas('galleryPhotos', ['bundles/own.jpg']);
    }

    public function test_bundle_in_subcategory_is_listed_among_its_products(): void
    {
        $group = $this->categoryGroup();
        $birds = $this->category($group, 'Для папуг', null);
        $food = $this->category($group, 'Корми', $birds->id);
        $otherSub = $this->category($group, 'Клітки', $birds->id);

        $feed = Product::query()->create([
            'title' => 'Корм для папуг окремо',
            'slug' => 'bird-food-single',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $birds->id,
            'category_value_id' => $food->id,
            'price' => 200,
            'is_available' => true,
            'variant_options' => [['option_group_id' => $group->id, 'option_value_ids' => [$food->id]]],
        ]);
        $bundle = Bundle::query()->create([
            'title' => 'Набір із трьох кормів',
            'slug' => 'three-feeds',
            'category_parent_value_id' => $birds->id,
            'category_value_id' => $food->id,
            'is_visible' => true,
            'is_active' => true,
        ]);
        BundleItem::query()->create(['bundle_id' => $bundle->id, 'product_id' => $feed->id, 'qty' => 3, 'sort_order' => 1]);

        $this->get(route('catalog.index', ['category' => $food->id]))
            ->assertOk()
            ->assertSee('Корм для папуг окремо')
            ->assertSee('Набір із трьох кормів');

        $this->get(route('catalog.index', ['category' => $birds->id]))
            ->assertOk()
            ->assertSee('Набір із трьох кормів');

        $this->get(route('catalog.index', ['category' => $otherSub->id]))
            ->assertOk()
            ->assertDontSee('Набір із трьох кормів');
    }

    /**
     * @return array{0: Bundle, 1: OptionValue, 2: Product}
     */
    private function bundleWithColorOption(): array
    {
        $group = $this->categoryGroup();
        $root = $this->category($group, 'Для гризунів', null);
        $colors = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'color-'.uniqid(),
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);
        $leopard = OptionValue::query()->create(['option_group_id' => $colors->id, 'name' => 'Леопард', 'slug' => 'leopard-'.uniqid(), 'sort_order' => 1, 'is_active' => true]);
        $yellow = OptionValue::query()->create(['option_group_id' => $colors->id, 'name' => 'Жовто-білий', 'slug' => 'yellow-'.uniqid(), 'sort_order' => 2, 'is_active' => true]);

        $product = Product::query()->create([
            'title' => 'Мʼякий комплект',
            'slug' => 'soft-set-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 1520,
            'is_available' => true,
            'photos' => ['products/1/leopard.jpg'],
            'variant_options' => [
                ['option_group_id' => $group->id, 'option_value_ids' => [$root->id]],
                [
                    'option_group_id' => $colors->id,
                    'option_value_ids' => [$leopard->id, $yellow->id],
                    'value_photos' => [
                        ['option_value_id' => $leopard->id, 'photos' => ['products/1/leopard.jpg']],
                        ['option_value_id' => $yellow->id, 'photos' => ['products/1/yellow.jpg']],
                    ],
                ],
            ],
        ]);
        $bundle = Bundle::query()->create([
            'title' => 'Комплект для гризунів',
            'slug' => 'rodent-set-'.uniqid(),
            'category_parent_value_id' => $root->id,
            'is_visible' => true,
            'is_active' => true,
        ]);
        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'option_value_ids' => [$yellow->id],
            'qty' => 1,
            'sort_order' => 1,
        ]);

        return [$bundle, $yellow, $product];
    }

    private function categoryGroup(): OptionGroup
    {
        return OptionGroup::query()->firstOrCreate(
            ['product_type' => OptionGroup::CATALOG_PRODUCT_TYPE, 'slug' => 'category'],
            ['name' => 'Категорія', 'selection_mode' => 'single', 'value_type' => 'text', 'is_active' => true]
        );
    }

    private function category(OptionGroup $group, string $name, ?int $parentId): OptionValue
    {
        return OptionValue::query()->create([
            'option_group_id' => $group->id,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => 'cat-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
