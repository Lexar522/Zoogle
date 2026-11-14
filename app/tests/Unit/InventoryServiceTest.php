<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    public function test_unsellable_variant_is_not_purchasable(): void
    {
        $product = new Product([
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
        ]);
        $variant = new ProductVariant([
            'is_available' => false,
            'is_low_stock' => false,
            'allows_preorder' => false,
            'quantity' => 0,
        ]);

        $service = app(InventoryService::class);

        $this->assertFalse($service->isPurchasable($product, $variant));
        $this->assertNotNull($service->purchaseError($product, $variant, 1));
    }

    public function test_quantity_limit_is_enforced_unless_preorder(): void
    {
        $product = new Product([
            'is_available' => true,
            'stock_status' => Product::STOCK_IN_STOCK,
        ]);
        $variant = new ProductVariant([
            'is_available' => true,
            'is_low_stock' => false,
            'allows_preorder' => false,
            'quantity' => 2,
        ]);

        $service = app(InventoryService::class);

        $this->assertNull($service->purchaseError($product, $variant, 2));
        $this->assertNotNull($service->purchaseError($product, $variant, 3));
        $this->assertNotNull($service->purchaseError($product, $variant, 2, 1));
    }

    public function test_preorder_skips_quantity_limit(): void
    {
        $product = new Product([
            'is_available' => true,
            'stock_status' => Product::STOCK_PREORDER,
        ]);
        $variant = new ProductVariant([
            'is_available' => false,
            'is_low_stock' => false,
            'allows_preorder' => false,
            'quantity' => 0,
        ]);

        $service = app(InventoryService::class);

        $this->assertTrue($service->isPurchasable($product, $variant));
        $this->assertNull($service->purchaseError($product, $variant, 5));
    }
}
