<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductGalleryOptionPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdp_gallery_shows_product_and_all_color_photos_before_selection(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/cage-front.jpg', 'front');
        Storage::disk('public')->put('products/cage-side.jpg', 'side');
        Storage::disk('public')->put('products/options/blue.jpg', 'blue');
        Storage::disk('public')->put('products/options/red.jpg', 'red');

        $root = $this->catalogRoot();

        $colorGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'color-gallery-test',
            'selection_mode' => 'single',
            'value_type' => 'color',
            'is_active' => true,
        ]);
        $blue = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Синій',
            'slug' => 'blue-gallery-test',
            'color_hex' => '#1e40af',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $red = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Червоний',
            'slug' => 'red-gallery-test',
            'color_hex' => '#b91c1c',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Клітка для гризунів',
            'slug' => 'klitka-gallery-option-photos',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 1778,
            'is_available' => true,
            'short_description' => 'Клітка',
            'photos' => ['products/cage-front.jpg', 'products/cage-side.jpg'],
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
                [
                    'option_group_id' => $colorGroup->id,
                    'option_value_ids' => [$blue->id, $red->id],
                    'value_photos' => [
                        [
                            'option_value_id' => $blue->id,
                            'photos' => ['products/options/blue.jpg'],
                        ],
                        [
                            'option_value_id' => $red->id,
                            // Фото товару, прив'язане ще й до кольору, не дублюється в галереї.
                            'photos' => ['products/options/red.jpg', 'products/cage-side.jpg'],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get(route('catalog.show', $product->slug));
        $response->assertOk();

        $html = (string) $response->getContent();

        // Основні фото товару — у головному кадрі та мініатюрах серверного рендеру.
        $this->assertStringContainsString('storage/products/cage-front.jpg', $html);
        $this->assertStringContainsString('storage/products/cage-side.jpg', $html);
        $this->assertSame(1, preg_match('/id="product-main-img"[^>]*cage-front\.jpg|cage-front\.jpg[^>]*id="product-main-img"/', $html));

        $config = $this->productShowConfig($html);

        // Єдина галерея: спершу фото товару, далі фото всіх кольорів — ще до вибору опції.
        $initialUrls = array_map(static fn (array $item): string => (string) $item['url'], $config['initialGalleryItems']);
        $this->assertCount(4, $initialUrls);
        $this->assertStringEndsWith('products/cage-front.jpg', $initialUrls[0]);
        $this->assertStringEndsWith('products/cage-side.jpg', $initialUrls[1]);
        $this->assertStringEndsWith('products/options/blue.jpg', $initialUrls[2]);
        $this->assertStringEndsWith('products/options/red.jpg', $initialUrls[3]);
        $this->assertStringContainsString('storage/products/options/red.jpg', $html);

        // Фото кольору є і в його опції, щоб JS перемкнув галерею на нього при кліку.
        $colorBlock = collect($config['optionBlocks'])->firstWhere('id', $colorGroup->id);
        $this->assertNotNull($colorBlock);
        $blueRow = collect($colorBlock['values'])->firstWhere('id', $blue->id);
        $this->assertSame(['products/options/blue.jpg'], $blueRow['gallery_photos']);
    }

    public function test_pdp_gallery_skips_color_photo_that_is_a_reupload_of_product_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/bed-beige.jpg', 'beige-bytes');
        Storage::disk('public')->put('products/bed-black.jpg', 'black-bytes');
        // Ті самі знімки, завантажені вдруге у фото кольорів під іншими іменами.
        Storage::disk('public')->put('products/options/black-copy.jpg', 'black-bytes');
        Storage::disk('public')->put('products/options/beige-copy.jpg', 'beige-bytes');

        $root = $this->catalogRoot();
        $colorGroup = OptionGroup::query()->create([
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'name' => 'Колір',
            'slug' => 'color-reupload-test',
            'selection_mode' => 'single',
            'value_type' => 'color',
            'is_active' => true,
        ]);
        $black = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Чорний',
            'slug' => 'black-reupload-test',
            'color_hex' => '#000000',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $beige = OptionValue::query()->create([
            'option_group_id' => $colorGroup->id,
            'name' => 'Бежевий',
            'slug' => 'beige-reupload-test',
            'color_hex' => '#d2b48c',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'title' => 'Лежанка',
            'slug' => 'lezanka-reupload-photos',
            'product_type' => OptionGroup::CATALOG_PRODUCT_TYPE,
            'category_parent_value_id' => $root->id,
            'category_value_id' => null,
            'price' => 893,
            'is_available' => true,
            'photos' => ['products/bed-beige.jpg', 'products/bed-black.jpg'],
            'variant_options' => [
                [
                    'option_group_id' => $root->option_group_id,
                    'option_value_ids' => [$root->id],
                ],
                [
                    'option_group_id' => $colorGroup->id,
                    'option_value_ids' => [$black->id, $beige->id],
                    'value_photos' => [
                        ['option_value_id' => $black->id, 'photos' => ['products/options/black-copy.jpg']],
                        ['option_value_id' => $beige->id, 'photos' => ['products/options/beige-copy.jpg']],
                    ],
                ],
            ],
        ]);

        $html = (string) $this->get(route('catalog.show', $product->slug))->assertOk()->getContent();
        $config = $this->productShowConfig($html);

        $paths = array_map(static fn (array $item): string => (string) $item['path'], $config['initialGalleryItems']);
        $this->assertSame(['products/bed-beige.jpg', 'products/bed-black.jpg'], $paths);
        $this->assertStringNotContainsString('black-copy.jpg', $html);

        // Клік по кольору перемикає на вже наявне фото товару.
        $colorBlock = collect($config['optionBlocks'])->firstWhere('id', $colorGroup->id);
        $this->assertSame(['products/bed-black.jpg'], collect($colorBlock['values'])->firstWhere('id', $black->id)['gallery_photos']);
        $this->assertSame(['products/bed-beige.jpg'], collect($colorBlock['values'])->firstWhere('id', $beige->id)['gallery_photos']);
    }

    public function test_pdp_script_merges_option_photos_into_main_gallery(): void
    {
        $js = (string) file_get_contents(resource_path('js/product-show-page.js'));

        $this->assertStringContainsString('mergeGalleryItems(initialGalleryItemsFrozen, colorGalleryItems, variantGalleryItems)', $js);
        $this->assertStringNotContainsString('setGallery(colorGalleryItems)', $js);
    }

    /**
     * @return array<string, mixed>
     */
    private function productShowConfig(string $html): array
    {
        $this->assertSame(
            1,
            preg_match('#<script type="application/json" id="product-show-config">(.*?)</script>#s', $html, $m)
        );

        return json_decode(html_entity_decode($m[1]), true, flags: JSON_THROW_ON_ERROR);
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
