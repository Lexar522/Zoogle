<?php

namespace Tests\Unit;

use App\Services\ProductShowPageService;
use App\Services\VariantPricingService;
use Tests\TestCase;

class ProductShowPageServiceTest extends TestCase
{
    public function test_variant_match_signatures_maps_distinct_option_combos(): void
    {
        $service = new ProductShowPageService(app(VariantPricingService::class));

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
        $service = new ProductShowPageService(app(VariantPricingService::class));

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
}
