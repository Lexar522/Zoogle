<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCommittedBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const BACKFILL_MIGRATION = 'database/migrations/2026_11_06_120000_backfill_inventory_committed_for_legacy_orders.php';

    public function test_backfill_marks_only_active_legacy_orders_as_committed(): void
    {
        $active = $this->legacyOrder(Order::STATUS_NEW, 'pending');
        $paid = $this->legacyOrder(Order::STATUS_PAID, 'paid');
        $cancelled = $this->legacyOrder(Order::STATUS_CANCELLED, 'pending');
        $failed = $this->legacyOrder(Order::STATUS_NEW, 'failed');
        $refunded = $this->legacyOrder(Order::STATUS_NEW, 'refunded');

        $this->runBackfill();

        $this->assertTrue($active->fresh()->inventory_committed);
        $this->assertTrue($paid->fresh()->inventory_committed);
        $this->assertFalse($cancelled->fresh()->inventory_committed);
        $this->assertFalse($failed->fresh()->inventory_committed);
        $this->assertFalse($refunded->fresh()->inventory_committed);
    }

    public function test_status_change_on_backfilled_legacy_order_does_not_take_stock_again(): void
    {
        $variant = $this->variantWithStock(5);
        $order = $this->legacyOrder(Order::STATUS_NEW, 'pending');
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'title_snapshot' => 'Legacy item',
            'option_value_ids' => [],
            'price' => 100,
            'qty' => 2,
            'line_total' => 200,
        ]);

        $this->runBackfill();

        $order->fresh()->update(['status' => Order::STATUS_PROCESSING]);

        $this->assertSame(5, (int) $variant->fresh()->quantity);
    }

    private function runBackfill(): void
    {
        (require base_path(self::BACKFILL_MIGRATION))->up();
    }

    /**
     * Замовлення до появи inventory_committed: прапорець false, хоча товар уже продано.
     */
    private function legacyOrder(string $status, string $paymentStatus): Order
    {
        $order = Order::query()->create([
            'customer_name' => 'Legacy',
            'customer_phone' => '380000000000',
            'total' => 200,
            'status' => Order::STATUS_NEW,
            'payment_status' => 'pending',
            'delivery_type' => Order::DELIVERY_PICKUP,
        ]);

        DB::table('orders')->where('id', $order->id)->update([
            'status' => $status,
            'payment_status' => $paymentStatus,
            'inventory_committed' => false,
        ]);

        return $order->fresh();
    }

    private function variantWithStock(int $quantity): ProductVariant
    {
        $group = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Каталог',
            'slug' => 'catalog-root-backfill',
            'selection_mode' => 'single',
            'value_type' => 'text',
            'is_active' => true,
        ]);
        $root = OptionValue::query()->create([
            'option_group_id' => $group->id,
            'name' => 'Корм',
            'slug' => 'food-backfill',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Legacy product',
            'slug' => 'legacy-product',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'price' => 100,
            'is_available' => true,
        ]);

        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'price' => 100,
            'quantity' => $quantity,
            'is_available' => true,
            'is_low_stock' => false,
            'is_visible' => true,
            'allows_preorder' => false,
            'is_sold' => false,
            'sort_order' => 1,
        ]);
    }
}
