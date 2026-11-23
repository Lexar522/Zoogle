@extends('layouts.shop')

@php
    $catalogSeo = $catalogSeo ?? [
        'title' => __('shop.catalog_page_title'),
        'description' => __('shop.catalog_meta_description'),
        'canonical' => route('catalog.index'),
        'robots' => request()->query() === [] ? 'index,follow' : 'noindex,follow',
        'og_title' => __('shop.catalog_og_title'),
        'og_description' => __('shop.catalog_og_description'),
        'indexable' => request()->query() === [],
    ];
@endphp

@section('title', $catalogSeo['title'])
@section('meta_description', $catalogSeo['description'])
@section('og_title', $catalogSeo['og_title'])
@section('og_description', $catalogSeo['og_description'])
@section('canonical_url', $catalogSeo['canonical'])
@section('robots', $catalogSeo['robots'])

@push('meta')
    @php
        $catalogSeoItems = [];
        if (! empty($catalogSeo['indexable']) && (int) ($filters['category'] ?? 0) > 0) {
            foreach ($listings ?? [] as $seoRecord) {
                if ($seoRecord instanceof \App\Models\Product && filled($seoRecord->slug)) {
                    $catalogSeoItems[] = [
                        '@type' => 'ListItem',
                        'position' => count($catalogSeoItems) + 1,
                        'name' => mt($seoRecord->title),
                        'url' => route('catalog.show', $seoRecord->slug),
                    ];
                } elseif ($seoRecord instanceof \App\Models\Bundle && filled($seoRecord->slug)) {
                    $catalogSeoItems[] = [
                        '@type' => 'ListItem',
                        'position' => count($catalogSeoItems) + 1,
                        'name' => mt($seoRecord->title),
                        'url' => route('bundles.show', $seoRecord->slug),
                    ];
                }
            }
        }
    @endphp
    @if ($catalogSeoItems !== [])
        <script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $catalogSeo['title'],
    'itemListElement' => $catalogSeoItems,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
    @endif
    @if (($catalogSeo['breadcrumb'] ?? []) !== [])
        <script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn (array $crumb, int $index): array => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb['name'],
            'item' => $crumb['url'],
        ],
        $catalogSeo['breadcrumb'],
        array_keys($catalogSeo['breadcrumb']),
    ),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
    @endif
@endpush

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@section('content')
    <div
        id="catalog-results"
        class="catalog-results catalog-results--listing"
        data-catalog-base="{{ route('catalog.index') }}"
        data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}"
        data-scroll-to-listing="{{ ! empty($showCatalogGrid) ? '1' : '0' }}"
    >
        @include('catalog.partials.results')
    </div>
@endsection

@push('scripts')
    @include('catalog.partials.shop-catalog-scripts')
@endpush
