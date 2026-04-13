<?php

namespace Tests\Unit;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Services\BundlePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundlePricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_subtotal_uses_product_base_price_when_multiple_variants_exist(): void
    {
        $first = $this->makeProduct('Набір 1', 'bundle-product-1', 3000);
        $second = $this->makeProduct('Набір 2', 'bundle-product-2', 1000);

        $first->variants()->create([
            'price' => 1000,
            'quantity' => 5,
            'is_available' => true,
            'is_visible' => true,
            'options' => [],
            'sort_order' => 1,
        ]);
        $first->variants()->create([
            'price' => 3000,
            'quantity' => 5,
            'is_available' => true,
            'is_visible' => true,
            'options' => [],
            'sort_order' => 2,
        ]);

        $bundle = Bundle::query()->create([
            'title' => 'Тестовий комплект',
            'slug' => 'test-bundle',
            'is_visible' => true,
            'is_active' => true,
        ]);

        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $first->id,
            'qty' => 1,
            'sort_order' => 1,
        ]);
        BundleItem::query()->create([
            'bundle_id' => $bundle->id,
            'product_id' => $second->id,
            'qty' => 1,
            'sort_order' => 2,
        ]);

        $quote = app(BundlePricingService::class)->quote($bundle);

        $this->assertSame('4000.00', number_format((float) $quote['subtotal'], 2, '.', ''));
        $this->assertSame('4000.00', number_format((float) $quote['total'], 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $quote['discount'], 2, '.', ''));
    }

    private function makeProduct(string $title, string $slug, float $price): Product
    {
        $root = $this->catalogRoot();

        return Product::query()->create([
            'title' => $title,
            'slug' => $slug,
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => $price,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
            ],
        ]);
    }

    private function catalogRoot(): OptionValue
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

        return OptionValue::query()->firstOrCreate(
            [
                'option_group_id' => $categoryGroup->id,
                'slug' => 'catalog-root',
            ],
            [
                'name' => 'Каталог',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }
}
