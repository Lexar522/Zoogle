@extends('layouts.shop')

@section('title', __('shop.cart_page_title'))
@section('robots', 'noindex,follow')

@section('content')
    <div class="card">
        <h1>{{ __('shop.cart_page_h1') }}</h1>
        <p class="muted">{{ __('shop.cart_page_lead') }}</p>
    </div>

    <div class="card" data-cart-page-content>
        @include('cart.partials.drawer-content', ['items' => $items, 'summary' => $summary])
    </div>
@endsection
