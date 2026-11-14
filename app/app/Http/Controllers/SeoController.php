<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductCareArticle;
use App\Models\ShopInfoPage;
use App\Services\CareArticleCatalog;
use App\Services\GoogleMerchantFeed;
use App\Support\PublicStorageUrl;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function __construct(
        private readonly CareArticleCatalog $careArticles,
    ) {}

    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /orders',
            'Disallow: /login',
            'Disallow: /auth/',
            'Disallow: /locale/',
            'Disallow: /preview/',
            'Allow: /catalog?category=',
            'Disallow: /catalog?',
            '',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ]);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Фід товарів для Google Merchant Center і каталогу Meta (Instagram / Facebook).
     */
    public function merchantFeed(Request $request, GoogleMerchantFeed $feed): Response
    {
        // Посилання у фіді абсолютні, тож кеш окремий для кожного домену (www і без www).
        $cacheKey = 'seo.merchant-feed.'.strtolower($request->getHttpHost());
        $xml = Cache::remember($cacheKey, now()->addHour(), function () use ($feed): string {
            return view('seo.merchant-feed', ['items' => $feed->items()])->render();
        });

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = [
            $this->urlEntry(route('home'), 'daily', '1.0'),
            $this->urlEntry(route('catalog.index'), 'daily', '0.9'),
            ...$this->categoryUrls(),
            $this->urlEntry(route('bundles.index'), 'weekly', '0.7'),
            $this->urlEntry(route('about'), 'monthly', '0.5'),
            $this->urlEntry(route('contacts'), 'monthly', '0.5'),
            $this->urlEntry(route('news'), 'weekly', '0.5'),
            $this->urlEntry(route('care.index'), 'weekly', '0.6'),
            $this->urlEntry(route('delivery-payment'), 'monthly', '0.6'),
            $this->urlEntry(route('privacy'), 'yearly', '0.3'),
        ];

        if (config('shop.seo_index_product_pages')) {
            Product::query()
                ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
                ->where('is_available', true)
                ->with(['publishedCareArticles:id,product_id,slug,updated_at,published_at', 'categories'])
                ->orderBy('id')
                ->get(['id', 'slug', 'photos', 'updated_at', 'published_at', 'category_parent_value_id', 'category_value_id'])
                ->each(function (Product $product) use (&$urls): void {
                    $urls[] = $this->urlEntry(
                        route('catalog.show', $product->slug),
                        'weekly',
                        '0.8',
                        $this->lastModified($product->updated_at, $product->published_at),
                        $this->productImageUrl($product),
                    );

                    if ($product->publishedCareArticles->isNotEmpty()
                        && $this->careArticles->productIsInAnimalBranch($product)) {
                        $urls[] = $this->urlEntry(
                            route('catalog.care.index', $product->slug),
                            'monthly',
                            '0.5',
                            $this->lastModified($product->updated_at, $product->publishedCareArticles->max('updated_at')),
                        );
                    }

                    foreach ($product->publishedCareArticles as $article) {
                        $urls[] = $this->urlEntry(
                            route('catalog.care.show', [$product->slug, $article->slug]),
                            'monthly',
                            '0.5',
                            $this->lastModified($article->updated_at, $article->published_at),
                        );
                    }
                });
        }

        Bundle::query()
            ->where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (Bundle $bundle) use (&$urls): void {
                $urls[] = $this->urlEntry(
                    route('bundles.show', $bundle->slug),
                    'weekly',
                    '0.7',
                    $this->lastModified($bundle->updated_at),
                );
            });

        if (SchemaCache::hasTable('product_care_articles')) {
            ProductCareArticle::query()
                ->published()
                ->orderBy('id')
                ->get(['slug', 'updated_at', 'published_at'])
                ->each(function (ProductCareArticle $article) use (&$urls): void {
                    $urls[] = $this->urlEntry(
                        $article->storefrontUrl(),
                        'monthly',
                        '0.5',
                        $this->lastModified($article->updated_at, $article->published_at),
                    );
                });
        }

        if (SchemaCache::hasTable('shop_info_pages')) {
            ShopInfoPage::query()
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->orderBy('id')
                ->get(['slug', 'updated_at'])
                ->each(function (ShopInfoPage $page) use (&$urls): void {
                    $urls[] = $this->urlEntry(
                        route('info.show', $page->slug),
                        'monthly',
                        '0.5',
                        $this->lastModified($page->updated_at),
                    );
                });
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Категорії, у яких уже є товари. Саме ці сторінки мають ловити запити на кшталт «клітка» чи «корм».
     *
     * @return list<array{loc: string, changefreq: string, priority: string, lastmod: ?string, image: ?string}>
     */
    private function categoryUrls(): array
    {
        $groupId = OptionGroup::systemCategoryGroupIdForCatalog();
        $table = (new Product)->getTable();
        if ($groupId <= 0
            || ! SchemaCache::hasColumn($table, 'category_value_id')
            || ! SchemaCache::hasColumn($table, 'category_parent_value_id')) {
            return [];
        }

        $rows = Product::query()
            ->where('is_available', true)
            ->get(['category_value_id', 'category_parent_value_id']);
        $ids = $rows
            ->flatMap(fn (Product $product): array => [
                (int) $product->category_value_id,
                (int) $product->category_parent_value_id,
            ])
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return OptionValue::query()
            ->where('option_group_id', $groupId)
            ->where('is_active', true)
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->get(['id', 'updated_at'])
            ->map(fn (OptionValue $category): array => $this->urlEntry(
                route('catalog.index', ['category' => $category->id]),
                'weekly',
                '0.7',
                $this->lastModified($category->updated_at),
            ))
            ->all();
    }

    /**
     * @return array{loc: string, changefreq: string, priority: string, lastmod: ?string, image: ?string}
     */
    private function urlEntry(string $loc, string $changefreq, string $priority, ?string $lastmod = null, ?string $image = null): array
    {
        return compact('loc', 'changefreq', 'priority', 'lastmod', 'image');
    }

    private function productImageUrl(Product $product): ?string
    {
        $path = $product->firstListedPhotoPath();
        if ($path === null || ! preg_match('/\.(jpe?g|png|webp|gif)$/i', $path)) {
            return null;
        }

        return PublicStorageUrl::forPath($path);
    }

    private function lastModified(mixed ...$dates): ?string
    {
        return collect($dates)
            ->filter()
            ->map(fn (mixed $date): Carbon => $date instanceof Carbon ? $date : Carbon::parse($date))
            ->max()
            ?->toDateString();
    }
}
