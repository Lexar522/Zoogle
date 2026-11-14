@extends('layouts.shop')

@section('title', __('shop.care_article_page_title', ['article' => mt($article->title), 'product' => mt($listing->title)]))
@section('meta_description', filled($article->excerpt) ? \Illuminate\Support\Str::limit(strip_tags(mt($article->excerpt)), 158) : __('shop.care_article_meta_fallback', ['product' => mt($listing->title)]))
@section('canonical_url', route('catalog.care.show', [$listing->slug, $article->slug]))
@section('robots', config('shop.seo_index_product_pages') ? 'index,follow' : 'noindex,follow')
@push('styles')
@vite('resources/css/storefront/care-show.css')
@endpush

@section('content')
    <div class="care-article-page">
        <article class="care-article">
            <header class="care-article__hero">
                <p class="care-article__eyebrow">{{ mt($listing->title) }}</p>
                <h1 class="care-article__title">{{ mt($article->title) }}</h1>
                @if (filled($article->excerpt))
                    <p class="care-article__lead">{{ mt($article->excerpt) }}</p>
                @endif
            </header>
            <div class="care-article__body">
                {!! $article->safeBodyHtml() !!}

                <div class="care-article-page__actions">
                    <a class="btn secondary" href="{{ route('care.index') }}">{{ __('shop.care_library_all') }}</a>
                    <a class="btn secondary" href="{{ route('catalog.care.index', $listing->slug) }}">Усі поради</a>
                    <a class="btn btn-buy" href="{{ route('catalog.show', $listing->slug) }}">До товару</a>
                </div>
            </div>
        </article>

        <aside class="care-sidebar" aria-label="Інші поради по догляду">
            <h2 class="care-sidebar__title">Поради по догляду</h2>
            <div class="care-sidebar__links">
                @foreach ($careArticles as $row)
                    <a
                        class="care-sidebar__link @if ($row->is($article)) is-active @endif"
                        href="{{ $row->storefrontUrl() }}"
                    >
                        {{ mt($row->title) }}
                    </a>
                @endforeach
            </div>
        </aside>
    </div>
@endsection
