@extends('account.layout')

@section('title', __('shop.account_page_title_order', ['number' => $order->number]))

@section('account_content')
    @if (session('error'))
        <p class="alert error account-flash" role="alert">{{ session('error') }}</p>
    @endif
    @include('account.partials.order-summary', [
        'order' => $order,
        'liqPayConfigured' => $liqPayConfigured ?? false,
    ])

    @include('partials.charity-invite')

    <div class="account-back-row">
        <a class="account-back-link" href="{{ route('account.orders.index') }}">‹ {{ __('shop.account_order_show_back') }}</a>
    </div>
@endsection
