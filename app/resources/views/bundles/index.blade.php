@extends('layouts.shop')

@section('title', __('shop.bundles_page_title'))
@section('meta_description', __('shop.bundles_meta_description'))
@section('canonical_url', route('bundles.index'))
@section('og_title', __('shop.bundles_og_title'))
@section('og_description', __('shop.bundles_og_description'))

@section('content')
    <div class="card">
        <h1>{{ __('shop.bundles_h1') }}</h1>
        <p class="muted">{{ __('shop.bundles_lead') }}</p>
    </div>
    @forelse ($bundles as $bundle)
        @php($q = $quotes[$bundle->id] ?? ['subtotal' => 0, 'discount' => 0, 'total' => 0])
        <div class="card">
            <h2><a href="{{ route('bundles.show', $bundle->slug) }}">{{ mt($bundle->title) }}</a></h2>
            @if (is_array($bundle->photos ?? null) && count($bundle->photos) && filled($bundle->photos[0]))
                <div style="margin: 8px 0;">
                    <img src="{{ asset('storage/' . $bundle->photos[0]) }}" alt="" width="320" height="280" decoding="async" loading="lazy" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--border);">
                </div>
            @endif
            @if ($bundle->short_description)
                <p class="muted">{{ \Illuminate\Support\Str::limit($bundle->short_description, 160) }}</p>
            @elseif ($bundle->description)
                <p class="muted">{{ \Illuminate\Support\Str::limit(strip_tags($bundle->description), 160) }}</p>
            @endif
            <p>
                @if ($q['discount'] > 0.001)
                    <span class="muted" style="text-decoration: line-through;">{{ number_format($q['subtotal'], 2) }} UAH</span>
                    <strong>{{ number_format($q['total'], 2) }} UAH</strong>
                    <span class="muted">{{ __('shop.bundles_discount_line', ['amount' => number_format($q['discount'], 2)]) }}</span>
                @else
                    <strong>{{ number_format($q['total'], 2) }} UAH</strong>
                @endif
            </p>
            <a class="btn" href="{{ route('bundles.show', $bundle->slug) }}">{{ __('shop.bundles_details') }}</a>
        </div>
    @empty
        <div class="card">
            <p>{{ __('shop.bundles_empty') }}</p>
        </div>
    @endforelse
@endsection
