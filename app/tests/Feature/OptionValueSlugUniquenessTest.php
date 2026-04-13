<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionValueSlugUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_auto_uniquifies_duplicate_option_value_slug_within_same_group(): void
    {
        $group = OptionGroup::query()->firstOrCreate(
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

        $parentA = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Батько A',
            'slug' => 'parent-a',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $parentB = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Батько B',
            'slug' => 'parent-b',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        OptionValue::query()->create([
            'option_group_id' => $group->id,
            'parent_id' => $parentA->id,
            'name' => 'Тест',
            'slug' => 'test',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $second = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'parent_id' => $parentB->id,
            'name' => 'Тест',
            'slug' => 'test',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertSame('test-2', $second->slug);
        $this->assertDatabaseHas('option_values', [
            'id' => $second->id,
            'option_group_id' => $group->id,
            'slug' => 'test-2',
        ]);
    }
}
