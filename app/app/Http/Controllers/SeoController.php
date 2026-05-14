<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\OptionGroup;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /orders',
            '',
            'Sitemap: '.route('seo.sitemap'),
            '',
        ]);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = [
            $this->urlEntry(route('home'), 'daily', '1.0'),
            $this->urlEntry(route('catalog.index'), 'daily', '0.9'),
            $this->urlEntry(route('bundles.index'), 'weekly', '0.7'),
            $this->urlEntry(route('about'), 'monthly', '0.5'),
            $this->urlEntry(route('contacts'), 'monthly', '0.5'),
            $this->urlEntry(route('news'), 'weekly', '0.5'),
            $this->urlEntry(route('delivery-payment'), 'monthly', '0.6'),
            $this->urlEntry(route('privacy'), 'yearly', '0.3'),
        ];

        Product::query()
            ->whereIn('product_type', OptionGroup::catalogListingProductTypes())
            ->where('is_available', true)
            ->with(['publishedCareArticles:id,product_id,slug,updated_at,published_at'])
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at', 'published_at'])
            ->each(function (Product $product) use (&$urls): void {
                $urls[] = $this->urlEntry(
                    route('catalog.show', $product->slug),
                    'weekly',
                    '0.8',
                    $this->lastModified($product->updated_at, $product->published_at),
                );

                if ($product->publishedCareArticles->isNotEmpty()) {
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

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @return array{loc: string, changefreq: string, priority: string, lastmod: ?string}
     */
    private function urlEntry(string $loc, string $changefreq, string $priority, ?string $lastmod = null): array
    {
        return compact('loc', 'changefreq', 'priority', 'lastmod');
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
