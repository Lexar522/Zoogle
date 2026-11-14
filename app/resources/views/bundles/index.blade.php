@extends('layouts.shop')

@section('title', __('shop.bundles_page_title'))
@section('meta_description', __('shop.bundles_meta_description'))
@section('canonical_url', route('bundles.index'))

@section('content')
    <div class="card">
        <h1>{{ __('shop.bundles_h1') }}</h1>
        <p class="muted">{{ __('shop.bundles_lead') }}</p>
    </div>
    <div class="bundles-index-grid">
        @forelse ($bundles as $bundle)
            @php($q = $quotes[$bundle->id] ?? ['subtotal' => 0, 'discount' => 0, 'total' => 0])
            @php($money = fn (float $amount): string => number_format($amount, 2, ',', ' ').' ₴')
            <article class="card bundles-index-card">
                @if (is_array($bundle->photos ?? null) && count($bundle->photos) && filled($bundle->photos[0]))
                    <a class="bundles-index-card__media" href="{{ route('bundles.show', $bundle->slug) }}" tabindex="-1" aria-hidden="true">
                        <img src="{{ \App\Support\ListingPhotoDerivative::cardUrlForPath($bundle->photos[0]) }}" alt="" loading="lazy">
                    </a>
                @endif
                <h2 class="bundles-index-card__title"><a href="{{ route('bundles.show', $bundle->slug) }}">{{ mt($bundle->title) }}</a></h2>
                @if ($bundle->short_description)
                    <p class="muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $bundle->short_description), 160) }}</p>
                @elseif ($bundle->description)
                    <p class="muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $bundle->description), 160) }}</p>
                @endif
                <p class="bundles-index-card__price">
                    @if ($q['discount'] > 0.001)
                        <span class="muted" style="text-decoration: line-through;">{{ $money((float) $q['subtotal']) }}</span>
                        <strong>{{ $money((float) $q['total']) }}</strong>
                        <span class="muted">{{ __('shop.bundles_discount_line', ['amount' => number_format($q['discount'], 2, ',', ' ')]) }}</span>
                    @else
                        <strong>{{ $money((float) $q['total']) }}</strong>
                    @endif
                </p>
                <a class="btn bundles-index-card__action" href="{{ route('bundles.show', $bundle->slug) }}">{{ __('shop.bundles_details') }}</a>
            </article>
        @empty
            <div class="card">
                <p>{{ __('shop.bundles_empty') }}</p>
            </div>
        @endforelse
    </div>
@endsection
