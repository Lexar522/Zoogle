@extends('layouts.shop')

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@section('title', __('shop.care_hub_page_title', ['product' => mt($listing->title)]))
@section('meta_description', __('shop.care_hub_meta_description', ['product' => mt($listing->title)]))
@section('canonical_url', route('catalog.care.index', $listing->slug))
@section('og_title', __('shop.care_hub_og_title', ['product' => mt($listing->title)]))
@section('og_description', __('shop.care_hub_og_description'))

@push('styles')
<style>
    .care-page {
        width: min(980px, 100%);
        margin: clamp(6px, 1vw, 14px) auto clamp(22px, 3vw, 38px);
        padding: clamp(18px, 3vw, 36px);
        border-radius: 22px;
        border: 1px solid #e8eaed;
        background: #fff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        color: #202124;
    }
    .care-page__eyebrow {
        display: inline-flex;
        margin: 0 0 0.8rem;
        padding: 0.4rem 0.75rem;
        border-radius: 999px;
        background: rgba(54, 125, 241, 0.1);
        color: #1d5fd6;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .care-page__title {
        margin: 0;
        color: #367df1;
        font-size: clamp(1.55rem, 4vw, 2.35rem);
        line-height: 1.08;
        letter-spacing: -0.04em;
    }
    .care-page__lead {
        max-width: 46rem;
        margin: 0.8rem 0 0;
        color: #5f6368;
        line-height: 1.55;
    }
    .care-page__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1.15rem;
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
        transition: transform 0.18s var(--ease-hover), box-shadow var(--duration-hover) var(--ease-hover);
    }
    .care-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.1);
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
    @media (max-width: 768px) {
        .care-page {
            width: calc(100% - (max(12px, env(safe-area-inset-left, 0px)) + max(12px, env(safe-area-inset-right, 0px))));
            max-width: calc(100% - (max(12px, env(safe-area-inset-left, 0px)) + max(12px, env(safe-area-inset-right, 0px))));
            margin: 0 auto max(14px, env(safe-area-inset-bottom, 0px) + 8px);
            padding: 12px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            box-sizing: border-box;
        }

        .care-page__eyebrow {
            margin: 0 auto 0.55rem;
            padding: 0.3rem 0.62rem;
            font-size: 0.66rem;
        }

        .care-page__title {
            font-size: 1.05rem;
            line-height: 1.22;
            letter-spacing: -0.02em;
            text-align: center;
        }

        .care-page__lead {
            max-width: none;
            margin: 0.55rem auto 0;
            font-size: 0.8rem;
            line-height: 1.45;
            text-align: center;
        }

        .care-page__actions {
            gap: 0.5rem;
            margin-top: 0.85rem;
        }

        .care-page__actions,
        .care-page__actions .btn {
            width: 100%;
        }

        .care-page__actions .btn {
            display: inline-flex;
            justify-content: center;
            min-height: 40px;
            padding: 8px 12px;
            font-size: 0.86rem;
            border-radius: 12px;
        }

        .care-grid {
            grid-template-columns: 1fr;
            gap: 0.65rem;
            margin-top: 0.85rem;
        }

        .care-card {
            padding: 0.85rem;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }

        .care-card:hover {
            transform: none;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }

        .care-card__meta {
            font-size: 0.66rem;
        }

        .care-card__title {
            margin-top: 0.4rem;
            font-size: 0.9rem;
        }

        .care-card__excerpt {
            margin-top: 0.45rem;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .care-card__more {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            align-self: flex-start;
            margin-top: 0.6rem;
            padding: 0.42rem 0.72rem;
            border-radius: 999px;
            border: 1px solid rgba(239, 56, 41, 0.22);
            background: rgba(239, 56, 41, 0.06);
            font-size: 0.76rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }
    }
</style>
@endpush

@section('content')
    <section class="care-page">
        <p class="care-page__eyebrow">Поради по догляду</p>
        <h1 class="care-page__title">{{ mt($listing->title) }}</h1>
        <p class="care-page__lead">
            Зібрали корисні матеріали для догляду, використання товару та щоденної турботи про улюбленця.
        </p>
        <div class="care-page__actions">
            <a class="btn secondary" href="{{ route('catalog.show', $listing->slug) }}">Назад до товару</a>
        </div>

        <div class="care-grid">
            @foreach ($careArticles as $article)
                <a class="care-card" href="{{ route('catalog.care.show', [$listing->slug, $article->slug]) }}">
                    <span class="care-card__meta">
                        {{ optional($article->published_at)->format('d.m.Y') ?? 'Порада' }}
                    </span>
                    <h2 class="care-card__title">{{ mt($article->title) }}</h2>
                    @if (filled($article->excerpt))
                        <p class="care-card__excerpt">{{ mt($article->excerpt) }}</p>
                    @endif
                    <span class="care-card__more">Читати статтю</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
