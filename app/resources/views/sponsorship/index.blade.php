@extends('layouts.shop')

@php($opika = \App\Models\ShopSponsorshipSetting::resolved())

@section('title', $opika['texts']['title'].' — ZOOGLE')
@section('meta_description', \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($opika['intro_html']))) ?: $opika['texts']['title'], 155, ''))
@section('canonical_url', route('sponsorship.index'))

@section('content')
    <section class="opika" aria-labelledby="opika-title">
        <header class="opika__intro">
            <h1 id="opika-title" class="opika__title">{{ $opika['texts']['title'] }}</h1>
            @if ($opika['intro_html'] !== '')
                <div class="opika__text info-page__body">{!! $opika['intro_html'] !!}</div>
            @endif
        </header>

        @if ($animals->isEmpty())
            <p class="opika__empty muted">Скоро тут з'являться наші підопічні.</p>
        @else
            <ul class="opika-grid" role="list">
                @foreach ($animals as $animal)
                    <li class="opika-card">
                        <a class="opika-card__link" href="{{ route('sponsorship.show', $animal) }}">
                            <span class="opika-card__media">
                                @if ($cover = $animal->coverImageUrl())
                                    <img src="{{ $cover }}" alt="{{ $animal->name }}" loading="lazy" decoding="async">
                                @else
                                    <span class="opika-card__placeholder" aria-hidden="true">🐾</span>
                                @endif
                            </span>
                            <span class="opika-card__body">
                                <span class="opika-card__name">{{ $animal->name }}</span>
                                @if (filled($animal->species))
                                    <span class="opika-card__species">{{ $animal->species }}</span>
                                @endif
                                @if (filled($animal->excerpt))
                                    <span class="opika-card__excerpt">{{ $animal->excerpt }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
