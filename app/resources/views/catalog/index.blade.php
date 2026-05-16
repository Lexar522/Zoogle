@extends('layouts.shop')

@section('title', __('shop.catalog_page_title'))
@section('meta_description', __('shop.catalog_meta_description'))
@section('canonical_url', route('catalog.index'))
@section('robots', request()->query() === [] ? 'index,follow' : 'noindex,follow')
@section('og_title', __('shop.catalog_og_title'))
@section('og_description', __('shop.catalog_meta_description'))

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
