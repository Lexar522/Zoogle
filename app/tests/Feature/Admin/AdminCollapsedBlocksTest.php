<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Pages\ManageHomeProductLists;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ShopHomeListItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сторінки налаштувань адмінки відкриваються зі згорнутими блоками: розгорнуті списки довго малюються.
 */
class AdminCollapsedBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actingAs(User::query()->where('email', 'admin@sitezoo.local')->firstOrFail());
    }

    public function test_settings_page_opens_with_sections_and_list_items_collapsed(): void
    {
        $hit = $this->product('Керамічна миска');
        ShopHomeListItem::query()->create(['list' => ShopHomeListItem::LIST_BESTSELLERS, 'product_id' => $hit->id, 'sort_order' => 0]);

        $html = (string) $this->get(ManageHomeProductLists::getUrl())->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'isCollapsed: true'), 'Обидві секції сторінки згорнуті');
        $this->assertStringNotContainsString('isCollapsed: false', $html);
        $this->assertStringContainsString('\\u0022isCollapsed\\u0022:true', $html, 'Рядок списку згорнутий');
        $this->assertStringNotContainsString('\\u0022isCollapsed\\u0022:false', $html);
    }

    public function test_product_select_on_home_lists_does_not_embed_whole_catalog(): void
    {
        $hit = $this->product('Керамічна миска');
        $this->product('Клітка для папуг');
        ShopHomeListItem::query()->create(['list' => ShopHomeListItem::LIST_BESTSELLERS, 'product_id' => $hit->id, 'sort_order' => 0]);

        $this->get(ManageHomeProductLists::getUrl())
            ->assertOk()
            ->assertSee('Керамічна миска')
            ->assertDontSee('Клітка для папуг');
    }

    public function test_record_forms_keep_sections_expanded(): void
    {
        $html = (string) $this->get(CreateProduct::getUrl())->assertOk()->getContent();

        $this->assertStringContainsString('isCollapsed: false', $html);
        $this->assertStringNotContainsString('isCollapsed: true', $html);
    }

    private function product(string $title): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => 'collapsed-'.uniqid(),
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'price' => 100,
            'is_available' => true,
        ]);
    }
}
