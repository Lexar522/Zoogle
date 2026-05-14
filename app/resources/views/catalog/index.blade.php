@extends('layouts.shop')

@section('title', 'Каталог — ZOOGLE')

@php
    $catalogMetaDescription = 'Каталог ZOOGLE: товари для домашніх улюбленців, зручні фільтри, комплекти, актуальні ціни та швидке оформлення замовлення.';
@endphp

@section('meta_description', $catalogMetaDescription)
@section('canonical_url', route('catalog.index'))
@section('robots', request()->query() === [] ? 'index,follow' : 'noindex,follow')
@section('og_title', 'Каталог товарів для тварин — ZOOGLE')
@section('og_description', $catalogMetaDescription)

@section('header_bottom')
    @include('catalog.partials.category-filters', ['inHeader' => true])
@endsection

@section('content')
    <div
        id="catalog-results"
        class="catalog-results catalog-results--listing"
        data-catalog-base="{{ route('catalog.index') }}"
        data-favorite-ids="{{ e(json_encode($favoriteProductIds ?? [])) }}"
    >
        @include('catalog.partials.results')
    </div>
@endsection

@push('scripts')
    @include('catalog.partials.shop-catalog-scripts')
@endpush
