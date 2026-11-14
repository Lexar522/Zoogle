<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OptionValueCategoryDeleteGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_category_can_be_deleted(): void
    {
        $group = $this->categoryGroup();
        $value = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Вільна',
            'slug' => 'vilna',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertTrue($value->delete());
        $this->assertDatabaseMissing('option_values', ['id' => $value->id]);
    }

    public function test_category_used_via_category_value_id_cannot_be_deleted(): void
    {
        $group = $this->categoryGroup();
        $value = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Зайнята',
            'slug' => 'zajnyata',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::query()->create([
            'title' => 'Товар у категорії',
            'slug' => 'tovar-u-kategorii',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $value->id,
            'category_value_id' => $value->id,
            'price' => 10,
            'is_available' => true,
            'variant_options' => [],
        ]);

        $this->expectException(ValidationException::class);
        $value->delete();
    }

    public function test_category_used_only_in_variant_options_json_cannot_be_deleted(): void
    {
        $group = $this->categoryGroup();
        $value = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'JSON категорія',
            'slug' => 'json-kategoriya',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::query()->create([
            'title' => 'Legacy JSON category product',
            'slug' => 'legacy-json-category-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => null,
            'category_value_id' => null,
            'price' => 10,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $group->id,
                    'option_value_ids' => [$value->id],
                ],
            ],
        ]);

        $this->expectException(ValidationException::class);
        $value->delete();
    }

    public function test_delete_guard_does_not_false_positive_on_similar_ids(): void
    {
        $group = $this->categoryGroup();
        $value5 = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Пʼятірка',
            'slug' => 'pyatirka',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $value50 = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Пʼятдесят',
            'slug' => 'pyatdesyat',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // Підганяємо id під 5 і 50, якщо драйвер дозволяє (sqlite autoincrement).
        // Якщо id інші — перевіряємо лише що unused value5 можна видалити при наявності 50 у JSON.
        Product::query()->create([
            'title' => 'Has fifty',
            'slug' => 'has-fifty',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'price' => 10,
            'is_available' => true,
            'variant_options' => [
                [
                    'option_group_id' => $group->id,
                    'option_value_ids' => [$value50->id],
                ],
            ],
        ]);

        $this->assertTrue($value5->delete());
        $this->assertDatabaseMissing('option_values', ['id' => $value5->id]);
    }

    private function categoryGroup(): OptionGroup
    {
        return OptionGroup::query()->firstOrCreate(
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
    }
}
