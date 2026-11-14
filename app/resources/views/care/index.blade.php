@extends('layouts.shop')

@section('title', __('shop.care_library_page_title'))
@section('meta_description', __('shop.care_library_meta_description'))
@section('canonical_url', route('care.index'))
@section('robots', 'index,follow')

@push('styles')
<style>
    .care-library {
        width: min(1100px, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(22px, 3vw, 38px);
        padding: clamp(18px, 3vw, 36px);
        border-radius: 22px;
        border: 1px solid #e8eaed;
        background: #fff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        color: #202124;
    }
    .care-library__title {
        margin: 0;
        color: #367df1;
        font-size: clamp(1.55rem, 4vw, 2.35rem);
        line-height: 1.08;
        letter-spacing: -0.04em;
    }
    .care-library__lead {
        margin: 0.7rem 0 0;
        max-width: 42rem;
        color: #5f6368;
        line-height: 1.5;
    }
    .care-library__search {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1.25rem;
    }
    .care-library__search input {
        flex: 1 1 220px;
        min-height: 44px;
        padding: 0 14px;
        border-radius: 14px;
        border: 1px solid #dadce0;
        font-size: 1rem;
    }
    .care-library__search button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 14px;
        border: 0;
        background: #367df1;
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }
    .care-library__back {
        display: inline-flex;
        align-items: center;
        margin-top: 1rem;
        color: #1a73e8;
        font-weight: 800;
        text-decoration: none;
    }
    .care-library__picked {
        margin: 0.85rem 0 0;
        color: #0f172a;
        font-size: 1.15rem;
        font-weight: 800;
    }
    .care-hub {
        display: grid;
        gap: 0.9rem;
        margin-top: 1.35rem;
    }
    .care-hub__group {
        padding: 1rem 1.1rem;
        border-radius: 18px;
        border: 1px solid #e8eaed;
        background: #f8fbff;
    }
    .care-hub__parent {
        display: inline-flex;
        align-items: center;
        color: #1a73e8;
        font-size: 1.08rem;
        font-weight: 800;
        text-decoration: none;
    }
    .care-hub__subs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin-top: 0.75rem;
    }
    .care-hub__sub {
        display: inline-flex;
        align-items: center;
        min-height: 36px;
        padding: 0 12px;
        border-radius: 999px;
        border: 1px solid #d7e3f8;
        background: #fff;
        color: #344054;
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 700;
    }
    .care-hub__sub:hover,
    .care-hub__parent:hover {
        color: #1558c0;
    }
    .care-hub__empty {
        margin: 1.25rem 0 0;
        color: #5f6368;
    }
    .care-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
        margin-top: clamp(1.25rem, 3vw, 2rem);
    }
    .care-card {
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 1.1rem;
        border-radius: 18px;
        border: 1px solid #e6ebf2;
        background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
        color: inherit;
        text-decoration: none;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    }
    .care-card__meta {
        color: #667085;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .care-card__title {
        margin: 0.55rem 0 0;
        color: #0f172a;
        font-size: 1.08rem;
        line-height: 1.28;
    }
    .care-card__excerpt {
        margin: 0.65rem 0 0;
        color: #5f6368;
        font-size: 0.92rem;
        line-height: 1.5;
    }
    .care-card__more {
        margin-top: auto;
        padding-top: 1rem;
        color: var(--color-cta);
        font-weight: 900;
    }
    .care-library__empty {
        margin: 1.5rem 0 0;
        color: #5f6368;
    }
    .care-library__pager {
        margin-top: 1.5rem;
    }
    @media (max-width: 768px) {
        .care-library {
            width: 100%;
            max-width: 100%;
            margin: 0 auto max(14px, env(safe-area-inset-bottom, 0px) + 8px);
            padding: 12px;
            border-radius: 16px;
            box-sizing: border-box;
        }
        .care-library__title { font-size: 1.15rem; text-align: center; }
        .care-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <section class="care-library">
        <h1 class="care-library__title">{{ __('shop.care_library_heading') }}</h1>
        <p class="care-library__lead">{{ __('shop.care_library_lead') }}</p>

        <form class="care-library__search" method="get" action="{{ route('care.index') }}" role="search">
            @if ($careCategoryId)
                <input type="hidden" name="category" value="{{ $careCategoryId }}">
            @endif
            <label class="visually-hidden" for="care-library-q">{{ __('shop.care_library_search_aria') }}</label>
            <input
                id="care-library-q"
                type="search"
                name="q"
                value="{{ $careSearch }}"
                maxlength="200"
                placeholder="{{ __('shop.care_library_search_placeholder') }}"
            >
            <button type="submit">{{ __('shop.care_library_search_submit') }}</button>
        </form>

        @if ($showCareArticles)
            <a class="care-library__back" href="{{ route('care.index') }}">{{ __('shop.care_library_back') }}</a>
            @if ($careCategoryName !== '')
                <h2 class="care-library__picked">{{ $careCategoryName }}</h2>
            @endif

            @if ($articles === null || $articles->isEmpty())
                <p class="care-library__empty">{{ __('shop.care_library_empty') }}</p>
            @else
                <div class="care-grid">
                    @foreach ($articles as $article)
                        <a class="care-card" href="{{ $article->storefrontUrl() }}">
                            <span class="care-card__meta">{{ $article->categoryPathLabel() ?: __('shop.care_library_card_meta') }}</span>
                            <h2 class="care-card__title">{{ mt($article->title) }}</h2>
                            @if (filled($article->excerpt))
                                <p class="care-card__excerpt">{{ mt($article->excerpt) }}</p>
                            @endif
                            <span class="care-card__more">{{ __('shop.care_library_read') }}</span>
                        </a>
                    @endforeach
                </div>
                <div class="care-library__pager">
                    {{ $articles->links() }}
                </div>
            @endif
        @elseif (($careAnimalGroups ?? []) !== [])
            <nav class="care-hub" aria-label="{{ __('shop.care_library_categories_aria') }}">
                @foreach ($careAnimalGroups as $group)
                    @if ((int) ($group['id'] ?? 0) <= 0)
                        @continue
                    @endif
                    <div class="care-hub__group">
                        <a class="care-hub__parent" href="{{ route('care.index', ['category' => $group['id']]) }}">{{ $group['name'] }}</a>
                        @if (($group['children'] ?? []) !== [])
                            <div class="care-hub__subs">
                                @foreach ($group['children'] as $child)
                                    <a class="care-hub__sub" href="{{ route('care.index', ['category' => $child['id']]) }}">{{ $child['name'] }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>
        @else
            <p class="care-hub__empty">{{ __('shop.care_library_categories_empty') }}</p>
        @endif
    </section>
@endsection
