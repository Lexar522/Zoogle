<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ListingDefaultOptionPrice;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Позиції фіду товарів. Ціна має збігатися з карткою товару, інакше Merchant Center відхилить позицію.
 */
class GoogleMerchantFeed
{
    /** Бренд у фіді та розмітці товару (Meta Commerce без бренду позицію не приймає). */
    public const BRAND = 'ZOOGLE';

    /**
     * Таксономія Google: «Animals & Pet Supplies > Pet Supplies».
     * Загальна «1» охоплює й «Live Animals», через що аксесуари («куля для хом'яків») Google позначав як продаж тварин.
     */
    private const GOOGLE_PRODUCT_CATEGORY = '2';

    /** Merchant Center приймає до 10 додаткових фото. */
    private const MAX_ADDITIONAL_IMAGES = 10;

    /** @var array<int, OptionValue>|null */
    private ?array $categoryNodes = null;

    public function __construct(
        private readonly VariantPricingService $pricing,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @return list<array{id: string, title: string, description: string, link: string, image: string, additional_images: list<string>, google_product_category: string, product_type: ?string, price: string, sale_price: ?string, availability: string}>
     */
    public function items(): array
    {
        app()->setLocale('uk');

        $items = [];

        Product::query()
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->with(SchemaCache::hasTable('product_categories') ? ['variants', 'categories'] : ['variants'])
            ->orderBy('id')
            ->chunk(200, function (Collection $products) use (&$items): void {
                foreach ($products as $product) {
                    $item = $this->item($product);
                    if ($item !== null) {
                        $items[] = $item;
                    }
                }
            });

        return $items;
    }

    /**
     * @return array{id: string, title: string, description: string, link: string, image: string, additional_images: list<string>, google_product_category: string, product_type: ?string, price: string, sale_price: ?string, availability: string}|null
     */
    private function item(Product $product): ?array
    {
        // Передзамовлення Merchant Center приймає лише з датою надходження, якої в магазині немає.
        if ($product->allowsPreorder()) {
            return null;
        }

        $images = $this->imageUrls($product);
        if ($images === []) {
            return null;
        }

        if ($this->isExcludedFromMerchant($product)) {
            return null;
        }

        $categoryPath = $this->categoryPath($product);

        $visibleVariants = $product->variants->filter(
            fn (ProductVariant $variant): bool => $variant->isVisibleOnStorefront()
        );
        $quote = $visibleVariants->isEmpty()
            ? $this->pricing->quoteProduct($product)
            : collect($this->pricing->quoteManyProductVariants($visibleVariants))
                ->sortBy(fn ($variantQuote): float => $variantQuote->effectivePrice)
                ->first();
        $addon = ListingDefaultOptionPrice::addonTotalForProduct($product);
        $effective = round($quote->effectivePrice + $addon, 2);
        if ($effective <= 0) {
            return null;
        }

        $strike = $quote->isOnSale() && $quote->strikePrice !== null
            ? round($quote->strikePrice + $addon, 2)
            : null;
        $title = $this->plainText(mt($product->title));

        return [
            'id' => (string) $product->id,
            'title' => Str::limit($title, 147),
            'description' => Str::limit($this->description($product, $title), 4900),
            'link' => route('catalog.show', $product->slug),
            'image' => $images[0],
            'additional_images' => array_slice($images, 1, self::MAX_ADDITIONAL_IMAGES),
            'google_product_category' => self::GOOGLE_PRODUCT_CATEGORY,
            'product_type' => $this->productType($categoryPath),
            'price' => $this->money($strike ?? $effective),
            'sale_price' => $strike !== null ? $this->money($effective) : null,
            'availability' => $this->availability($product, $visibleVariants),
        ];
    }

    /**
     * @param  Collection<int, ProductVariant>  $visibleVariants
     */
    private function availability(Product $product, Collection $visibleVariants): string
    {
        $purchasable = $visibleVariants->isEmpty()
            ? $this->inventory->isPurchasable($product, null)
            : $visibleVariants->contains(fn (ProductVariant $variant): bool => $this->inventory->isPurchasable($product, $variant));

        return $purchasable ? 'in_stock' : 'out_of_stock';
    }

    /**
     * @return list<string>
     */
    private function imageUrls(Product $product): array
    {
        return collect($product->storefrontGalleryItems())
            ->filter(fn (array $item): bool => ($item['type'] ?? 'image') === 'image' && filled($item['url'] ?? null))
            ->map(fn (array $item): string => (string) $item['url'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Живих тварин і живий корм Google рекламувати забороняє: позначка на самому товарі
     * або на будь-якому рівні гілки його основної чи додаткової категорії.
     */
    public function isExcludedFromMerchant(Product $product): bool
    {
        if ($product->exclude_from_merchant_feed) {
            return true;
        }

        $categoryIds = [
            $product->resolvedCatalogCategoryNodeId(),
            (int) ($product->category_parent_value_id ?? 0),
        ];
        if ($product->relationLoaded('categories')) {
            $categoryIds = [...$categoryIds, ...$product->categories->pluck('id')->map(fn ($id): int => (int) $id)->all()];
        }

        foreach (array_unique($categoryIds) as $categoryId) {
            foreach ($this->pathToRoot($categoryId) as $node) {
                if ($node->exclude_from_merchant_feed) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Категорія товару від кореня до листа.
     *
     * @return list<OptionValue>
     */
    private function categoryPath(Product $product): array
    {
        return array_reverse($this->pathToRoot($product->resolvedCatalogCategoryNodeId()));
    }

    /**
     * @return list<OptionValue> від вузла до кореня
     */
    private function pathToRoot(int $id): array
    {
        $nodes = $this->categoryNodes();
        $path = [];
        while ($id > 0 && isset($nodes[$id]) && ! isset($path[$id])) {
            $path[$id] = $nodes[$id];
            $id = (int) ($nodes[$id]->parent_id ?? 0);
        }

        return array_values($path);
    }

    /**
     * Шлях категорії магазину для g:product_type, напр. «Гризуни > Корм».
     *
     * @param  list<OptionValue>  $categoryPath
     */
    private function productType(array $categoryPath): ?string
    {
        $names = array_values(array_filter(array_map(
            fn (OptionValue $node): string => $this->plainText(mt($node->name)),
            $categoryPath
        ), fn (string $name): bool => $name !== ''));

        return $names === [] ? null : Str::limit(implode(' > ', $names), 750, '');
    }

    /**
     * @return array<int, OptionValue>
     */
    private function categoryNodes(): array
    {
        if ($this->categoryNodes === null) {
            $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
            $this->categoryNodes = $groupId > 0
                ? OptionValue::query()->where('option_group_id', $groupId)->get(SchemaCache::hasColumn('option_values', 'exclude_from_merchant_feed')
                    ? ['id', 'parent_id', 'name', 'exclude_from_merchant_feed']
                    : ['id', 'parent_id', 'name'])->keyBy('id')->all()
                : [];
        }

        return $this->categoryNodes;
    }

    private function description(Product $product, string $fallback): string
    {
        foreach ([$product->safeDescriptionHtml(), $product->safeShortDescriptionHtml()] as $html) {
            $text = $this->plainText($html);
            if ($text !== '') {
                return $text;
            }
        }

        return $fallback;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '').' UAH';
    }

    private function plainText(mixed $value): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', (string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $text));
    }
}
