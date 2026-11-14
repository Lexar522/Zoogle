<?php

namespace App\Services;

use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ListingDefaultOptionPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Позиції фіду товарів. Ціна має збігатися з карткою товару, інакше Merchant Center відхилить позицію.
 */
class GoogleMerchantFeed
{
    public function __construct(
        private readonly VariantPricingService $pricing,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @return list<array{id: string, title: string, description: string, link: string, image: string, price: string, sale_price: ?string, availability: string}>
     */
    public function items(): array
    {
        app()->setLocale('uk');

        $items = [];

        Product::query()
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->with('variants')
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
     * @return array{id: string, title: string, description: string, link: string, image: string, price: string, sale_price: ?string, availability: string}|null
     */
    private function item(Product $product): ?array
    {
        // Передзамовлення Merchant Center приймає лише з датою надходження, якої в магазині немає.
        if ($product->allowsPreorder()) {
            return null;
        }

        $image = $this->imageUrl($product);
        if ($image === null) {
            return null;
        }

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
            'image' => $image,
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

    private function imageUrl(Product $product): ?string
    {
        foreach ($product->storefrontGalleryItems() as $item) {
            if (($item['type'] ?? 'image') === 'image' && filled($item['url'] ?? null)) {
                return (string) $item['url'];
            }
        }

        return null;
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
