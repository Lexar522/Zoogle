<?php

namespace Tests\Unit;

use App\Services\ProductShowPageService;
use Tests\TestCase;

class ProductShowPageServiceTest extends TestCase
{
    public function test_variant_match_signatures_maps_distinct_option_combos(): void
    {
        $service = app(ProductShowPageService::class);

        $optionBlocks = [
            ['id' => 10, 'affects_variant_matching' => true],
            ['id' => 20, 'affects_variant_matching' => false],
        ];

        $variantsPayload = [
            ['id' => 100, 'options' => [10 => 1, 20 => 99]],
            ['id' => 200, 'options' => [10 => 2, 20 => 99]],
        ];

        $map = $service->variantMatchSignaturesByOptionBlocks($optionBlocks, $variantsPayload);

        $this->assertSame(100, $map['10:1']);
        $this->assertSame(200, $map['10:2']);
    }

    public function test_variant_match_signatures_keeps_lower_id_on_collision(): void
    {
        $service = app(ProductShowPageService::class);

        $optionBlocks = [
            ['id' => 1, 'affects_variant_matching' => true],
        ];

        $variantsPayload = [
            ['id' => 50, 'options' => [1 => 5]],
            ['id' => 30, 'options' => [1 => 5]],
        ];

        $map = $service->variantMatchSignaturesByOptionBlocks($optionBlocks, $variantsPayload);

        $this->assertSame(30, $map['1:5']);
    }

    public function test_default_option_selection_prefers_base_price_value(): void
    {
        $service = app(ProductShowPageService::class);
        $method = new \ReflectionMethod(ProductShowPageService::class, 'defaultOptionSelectionForBlocks');
        $method->setAccessible(true);

        $selection = $method->invoke($service, [
            [
                'id' => 7,
                'selection_mode' => 'multiple',
                'values' => [
                    ['id' => 101, 'price' => null, 'is_base_price' => true],
                    ['id' => 102, 'price' => 125.0, 'is_base_price' => false],
                    ['id' => 103, 'price' => 250.0, 'is_base_price' => false],
                ],
            ],
            [
                'id' => 8,
                'selection_mode' => 'single',
                'values' => [
                    ['id' => 201, 'price' => 50.0, 'is_base_price' => false],
                    ['id' => 202, 'price' => 10.0, 'is_base_price' => false],
                ],
            ],
        ]);

        $this->assertSame([101], $selection[7]);
        $this->assertSame(202, $selection[8]);
    }

    public function test_default_option_selection_prefers_admin_default_over_minimum(): void
    {
        $service = app(ProductShowPageService::class);
        $method = new \ReflectionMethod(ProductShowPageService::class, 'defaultOptionSelectionForBlocks');
        $method->setAccessible(true);

        $selection = $method->invoke($service, [
            [
                'id' => 9,
                'selection_mode' => 'single',
                'default_option_value_id' => 301,
                'values' => [
                    ['id' => 301, 'price' => 530.0, 'is_base_price' => false],
                    ['id' => 302, 'price' => 405.0, 'is_base_price' => false],
                    ['id' => 303, 'price' => 1030.0, 'is_base_price' => false],
                ],
            ],
        ]);

        $this->assertSame(301, $selection[9]);
    }

    public function test_match_variant_id_uses_matching_option_signature(): void
    {
        $service = app(ProductShowPageService::class);
        $blocks = [
            ['id' => 10, 'affects_variant_matching' => true],
            ['id' => 20, 'affects_variant_matching' => false],
        ];
        $payload = [
            ['id' => 100, 'options' => [10 => 1, 20 => 99], 'price' => 200],
            ['id' => 200, 'options' => [10 => 2, 20 => 99], 'price' => 350],
        ];

        $this->assertSame(200, $service->matchVariantIdFromSelection($blocks, $payload, [10 => 2, 20 => 99]));
        $this->assertSame(100, $service->matchVariantIdFromSelection($blocks, $payload, [10 => 1]));
        $this->assertNull($service->matchVariantIdFromSelection($blocks, $payload, [10 => 9]));
    }
}
