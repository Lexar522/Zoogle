@extends('layouts.shop')

@php
    $opika = \App\Models\ShopSponsorshipSetting::resolved();
    $gallery = $animal->galleryItems();
@endphp

@section('title', $animal->name.' — '.$opika['texts']['title'].' — ZOOGLE')
@section('meta_description', \Illuminate\Support\Str::limit($animal->excerpt ?: trim(preg_replace('/\s+/u', ' ', strip_tags($animal->storyHtml()))), 155, ''))
@section('canonical_url', route('sponsorship.show', $animal))
@if ($cover = $animal->coverImageUrl())
    @section('og_image', $cover)
@endif

@section('content')
    <article class="opika opika-animal" aria-labelledby="opika-animal-title">
        <nav class="opika__back"><a href="{{ route('sponsorship.index') }}">← {{ $opika['texts']['title'] }}</a></nav>

        <div class="opika-animal__layout">
            <div class="opika-animal__gallery">
                @forelse ($gallery as $item)
                    <figure class="opika-animal__photo @if ($loop->first) opika-animal__photo--main @endif">
                        @if (($item['type'] ?? 'image') === 'video')
                            <video src="{{ $item['url'] }}" controls playsinline preload="metadata" @if (! empty($item['poster_url'])) poster="{{ $item['poster_url'] }}" @endif></video>
                        @else
                            <img src="{{ $item['url'] }}" alt="{{ $animal->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" style="object-position: {{ $item['object_position'] ?? '50% 50%' }}">
                        @endif
                    </figure>
                @empty
                    <figure class="opika-animal__photo opika-animal__photo--main opika-card__placeholder" aria-hidden="true">🐾</figure>
                @endforelse
            </div>

            <div class="opika-animal__info">
                <h1 id="opika-animal-title" class="opika__title">{{ $animal->name }}</h1>
                @if (filled($animal->species))
                    <p class="opika-card__species">{{ $animal->species }}</p>
                @endif

                @if ($terms !== [])
                    <details class="opika-become" @if ($errors->any()) open @endif>
                        <summary class="btn btn-buy opika-become__toggle">{{ $opika['texts']['become_label'] }}</summary>
                        <div class="opika-become__panel">
                            <p class="opika-become__title">{{ $opika['texts']['choose_term_title'] }}</p>
                            <ul class="opika-terms" role="list">
                                @foreach ($terms as $term => $price)
                                    <li>
                                        <a class="opika-term" href="{{ route('sponsorship.apply', ['animal' => $animal, 'term' => $term]) }}">
                                            <span class="opika-term__label">{{ \App\Models\ShopSponsorshipSetting::termLabel($term) }}</span>
                                            <span class="opika-term__price">{{ \App\Support\ShopPrice::amount($price) }} ₴</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>
                @endif

                @if (($story = $animal->storyHtml()) !== '')
                    <div class="opika-animal__story info-page__body">{!! $story !!}</div>
                @endif
            </div>
        </div>

        @if ($sponsors->isNotEmpty())
            <section class="opika-sponsors" aria-labelledby="opika-sponsors-title">
                <h2 id="opika-sponsors-title" class="opika-sponsors__title">{{ $opika['texts']['sponsors_title'] }}</h2>
                <ul class="opika-sponsors__list" role="list">
                    @foreach ($sponsors as $sponsor)
                        <li class="opika-sponsor">
                            @if ($photo = $sponsor->publicPhotoUrl())
                                <img class="opika-sponsor__photo" src="{{ $photo }}" alt="{{ $sponsor->publicName() }}" loading="lazy" decoding="async">
                            @else
                                <span class="opika-sponsor__photo opika-sponsor__photo--empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($sponsor->publicName(), 0, 1)) }}</span>
                            @endif
                            <span class="opika-sponsor__name">{{ $sponsor->publicName() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
@endsection
