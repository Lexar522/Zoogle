@extends('layouts.shop')

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@section('title', __('shop.care_article_page_title', ['article' => mt($article->title), 'product' => mt($listing->title)]))
@section('meta_description', filled($article->excerpt) ? \Illuminate\Support\Str::limit(strip_tags(mt($article->excerpt)), 158) : __('shop.care_article_meta_fallback', ['product' => mt($listing->title)]))
@section('canonical_url', route('catalog.care.show', [$listing->slug, $article->slug]))
@section('og_type', 'article')
@section('og_title', __('shop.care_article_og_title', ['article' => mt($article->title), 'product' => mt($listing->title)]))
@section('og_description', filled($article->excerpt) ? \Illuminate\Support\Str::limit(strip_tags(mt($article->excerpt)), 158) : __('shop.care_article_og_description_fallback'))

@push('styles')
<style>
    .care-article-page {
        width: min(1100px, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(22px, 3vw, 38px);
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(240px, 320px);
        gap: clamp(1rem, 2.5vw, 1.5rem);
        align-items: start;
        color: #202124;
    }
    .care-article {
        border-radius: 22px;
        border: 1px solid #e8eaed;
        background: #fff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }
    .care-article__hero {
        padding: clamp(1.25rem, 3vw, 2.25rem);
        background:
            radial-gradient(circle at 92% 8%, rgba(254, 180, 0, 0.22), transparent 34%),
            linear-gradient(135deg, #1e5bb8 0%, #367df1 52%, #5ca0ff 100%);
        color: #fff;
    }
    .care-article__eyebrow {
        display: inline-flex;
        margin: 0 0 0.8rem;
        padding: 0.36rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.16);
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .care-article__title {
        max-width: 48rem;
        margin: 0;
        font-size: clamp(1.65rem, 4vw, 2.55rem);
        line-height: 1.07;
        letter-spacing: -0.045em;
    }
    .care-article__lead {
        max-width: 44rem;
        margin: 0.85rem 0 0;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.55;
    }
    .care-article__body {
        padding: clamp(1.1rem, 3vw, 2rem);
        color: #3c4043;
        font-size: 1rem;
        line-height: 1.72;
    }
    .care-article__body > *:first-child {
        margin-top: 0;
    }
    .care-article__body > *:last-child {
        margin-bottom: 0;
    }
    .care-article__body h2,
    .care-article__body h3,
    .care-article__body h4 {
        margin: 1.3em 0 0.55em;
        color: #202124;
        line-height: 1.25;
        letter-spacing: -0.02em;
    }
    .care-article__body p {
        margin: 0 0 0.9em;
    }
    .care-article__body ul,
    .care-article__body ol {
        margin: 0.45em 0 1em 1.25em;
        padding: 0;
    }
    .care-article__body li {
        margin: 0.35em 0;
    }
    .care-article__body a {
        color: #1a73e8;
        text-decoration: underline;
        text-underline-offset: 3px;
        overflow-wrap: anywhere;
    }
    .care-article__body img {
        display: block;
        max-width: 100%;
        height: auto;
        margin: 1rem auto;
        border-radius: 18px;
        border: 1px solid #edf1f6;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
    }
    .care-article-video {
        position: relative;
        width: 100%;
        margin: 1.2rem 0;
        aspect-ratio: 16 / 9;
        border-radius: 18px;
        overflow: hidden;
        background: #0f172a;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
    }
    .care-article-video iframe {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
    .care-sidebar {
        position: sticky;
        top: clamp(16px, calc(var(--header-sticky-offset) + 12px), 96px);
        border-radius: 20px;
        border: 1px solid #e8eaed;
        background: #fff;
        padding: 1rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
    }
    .care-sidebar__title {
        margin: 0 0 0.8rem;
        color: #367df1;
        font-size: 1rem;
        font-weight: 900;
    }
    .care-sidebar__links {
        display: grid;
        gap: 0.55rem;
    }
    .care-sidebar__link {
        display: block;
        padding: 0.7rem 0.75rem;
        border-radius: 12px;
        color: #344054;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 700;
        line-height: 1.3;
        background: #f8fafc;
        border: 1px solid transparent;
    }
    .care-sidebar__link.is-active {
        color: #1d5fd6;
        border-color: rgba(54, 125, 241, 0.2);
        background: #eaf2ff;
    }
    .care-article-page__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1rem;
    }
    .care-article-page__actions .btn-buy {
        background: var(--color-cta);
        color: #fff !important;
        border-color: transparent;
        box-shadow: 0 8px 20px rgba(var(--color-cta-rgb), 0.25);
    }
    .care-article-page__actions .btn {
        text-decoration: none !important;
        border-bottom: 0;
    }
    .care-article-page__actions .btn-buy:hover {
        background: var(--color-cta-hover);
        color: #fff !important;
        text-decoration: none !important;
    }
    @media (max-width: 860px) {
        .care-article-page {
            grid-template-columns: 1fr;
            margin-top: 0;
        }
        .care-sidebar {
            position: static;
            order: -1;
        }
    }
    @media (max-width: 768px) {
        .care-article {
            border-radius: 18px;
        }
        .care-article__hero,
        .care-article__body {
            padding: 1rem;
        }
        .care-article-page__actions,
        .care-article-page__actions .btn {
            width: 100%;
        }
        .care-article-page__actions .btn {
            display: inline-flex;
            justify-content: center;
        }
    }
</style>
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
                        href="{{ route('catalog.care.show', [$listing->slug, $row->slug]) }}"
                    >
                        {{ mt($row->title) }}
                    </a>
                @endforeach
            </div>
        </aside>
    </div>
@endsection
