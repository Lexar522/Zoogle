@extends('layouts.shop')

@section('title', __('shop.order_track_page_title', ['number' => $order->number]))
@section('robots', 'noindex,follow')

@push('styles')
    @vite('resources/css/storefront/order-track.css')
@endpush

@section('content')
    <div class="order-track">
        @include('orders.partials.summary', ['order' => $order])
        @include('orders.partials.updates', ['order' => $order, 'toneClass' => 'order-updates'])

        @include('partials.charity-invite')

        <div class="order-track__actions">
            <a class="btn btn-buy" href="{{ route('catalog.index') }}">{{ __('shop.order_track_to_catalog') }}</a>
        </div>
    </div>
@endsection
