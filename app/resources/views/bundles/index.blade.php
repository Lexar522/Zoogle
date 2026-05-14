@extends('layouts.shop')

@section('title', 'Комплекти — ZOOGLE')
@section('meta_description', 'Готові комплекти товарів ZOOGLE для домашніх улюбленців: зручні набори, прозора ціна та швидке додавання в кошик.')
@section('canonical_url', route('bundles.index'))
@section('og_title', 'Комплекти товарів для тварин — ZOOGLE')
@section('og_description', 'Готові набори товарів для домашніх улюбленців з прозорою ціною та зручним онлайн-замовленням.')

@section('content')
    <div class="card">
        <h1>Комплекти</h1>
        <p class="muted">Готові набори товарів з прозорою ціною.</p>
    </div>
    @forelse ($bundles as $bundle)
        @php($q = $quotes[$bundle->id] ?? ['subtotal' => 0, 'discount' => 0, 'total' => 0])
        <div class="card">
            <h2><a href="{{ route('bundles.show', $bundle->slug) }}">{{ $bundle->title }}</a></h2>
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
                    <span class="muted">(знижка {{ number_format($q['discount'], 2) }} UAH)</span>
                @else
                    <strong>{{ number_format($q['total'], 2) }} UAH</strong>
                @endif
            </p>
            <a class="btn" href="{{ route('bundles.show', $bundle->slug) }}">Деталі</a>
        </div>
    @empty
        <div class="card">
            <p>Наразі немає активних комплектів.</p>
        </div>
    @endforelse
@endsection
