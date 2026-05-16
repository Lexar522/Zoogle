@extends('account.layout')

@section('title', __('shop.account_page_title_order', ['number' => $order->number]))

@section('account_content')
    @if (session('error'))
        <p class="alert error" role="alert" style="margin-bottom:1rem;">{{ session('error') }}</p>
    @endif
    @include('account.partials.order-summary', [
        'order' => $order,
        'liqPayConfigured' => $liqPayConfigured ?? false,
    ])

    <div class="account-back-row">
        <a class="btn secondary" href="{{ route('account.orders.index') }}">{{ __('shop.account_order_show_back') }}</a>
    </div>
@endsection
